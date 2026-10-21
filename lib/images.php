<?php
/**
 * Images envoyées depuis le back-office : même traitement que le pipeline manuel du projet
 * (cf. CLAUDE.md, section Images), avec GD puisque le serveur n'a ni Imagick ni exec() :
 *  - l'image est DÉCODÉE puis RÉÉCRITE : un fichier piégé ne passe jamais tel quel ;
 *  - orientation EXIF appliquée aux pixels, métadonnées supprimées ;
 *  - 1400 px max (photo) / 600 px (logo) ; JPEG qualité 82 progressif + jumeau .webp ;
 *  - logo en PNG (transparence gardée) + jumeau .webp sans perte ;
 *  - nom de fichier toujours nouveau (cache navigateur d'un an sur les images).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class ImageRejected extends RuntimeException {}

/**
 * @param string $tmp  fichier reçu
 * @param string $kind 'photo' | 'logo'
 * @return string chemin public (/uploads/entreprises/<slug>/…)
 */
function vib_image_store(string $tmp, string $kind, string $slug): string
{
    if (!in_array($kind, ['photo', 'logo'], true) || !preg_match('/^[a-z0-9-]+$/', $slug)) throw new InvalidArgumentException('Paramètres invalides');
    $info = @getimagesize($tmp);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        throw new ImageRejected('Format non pris en charge : envoyez une photo JPEG, PNG ou WebP.');
    }
    [$w, $h] = $info;
    if ($w < 200 || $h < 200) throw new ImageRejected("Image trop petite ({$w} × {$h} px) : 200 px minimum.");
    if ($w * $h > 40_000_000) throw new ImageRejected('Image trop grande.');

    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($tmp),
        IMAGETYPE_PNG  => @imagecreatefrompng($tmp),
        IMAGETYPE_WEBP => @imagecreatefromwebp($tmp),
    };
    if (!$src) throw new ImageRejected('Image illisible ou endommagée.');

    // Photo de téléphone : appliquer la rotation indiquée par l'EXIF (sinon elle s'affiche couchée)
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
        $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; } }
    }

    $max = $kind === 'logo' ? 600 : 1400;
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($kind === 'logo') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // photo avec transparence : fond blanc
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    $rel = "/entreprises/$slug";
    $dir = vib_config()['uploads_dir'] . $rel;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Dossier des photos inaccessible');
    $base = $kind . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $ext = $kind === 'logo' ? 'png' : 'jpg';
    $file = "$dir/$base.$ext";
    $tmpOut = "$dir/.$base.tmp";

    // Écrire puis renommer : jamais un fichier à moitié écrit servi au public
    if ($kind === 'logo') {
        $ok = imagepng($dst, $tmpOut, 9) && imagewebp($dst, "$dir/.$base.webp.tmp", IMG_WEBP_LOSSLESS);
    } else {
        imageinterlace($dst, true);
        $ok = imagejpeg($dst, $tmpOut, 82) && imagewebp($dst, "$dir/.$base.webp.tmp", 82);
    }
    imagedestroy($dst);
    if (!$ok || !rename("$dir/.$base.webp.tmp", "$dir/$base.webp") || !rename($tmpOut, $file)) {
        @unlink($tmpOut); @unlink("$dir/.$base.webp.tmp"); @unlink("$dir/$base.webp");
        throw new RuntimeException('Enregistrement de l’image impossible');
    }
    @chmod($file, 0644); @chmod("$dir/$base.webp", 0644);
    return "/uploads$rel/$base.$ext";
}
