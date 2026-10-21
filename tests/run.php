<?php
/**
 * Tests automatiques du socle du back-office (moteur d'enregistrement + vérification Cloudflare Access).
 * Lancement : php tests/run.php   (n'utilise qu'un dossier temporaire, ne touche à aucune donnée réelle)
 */

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/vib-tests-' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
putenv("VIB_DATA_DIR=$tmp");
putenv('CF_ACCESS_TEAM_DOMAIN=https://vib.cloudflareaccess.com');
putenv('CF_ACCESS_AUD=aud-test-123');
require __DIR__ . '/../lib/store.php';
require __DIR__ . '/../lib/access.php';

$ok = 0; $ko = 0;
function check(string $label, bool $cond): void { global $ok, $ko; $cond ? $ok++ : $ko++; echo ($cond ? '  ✓ ' : '  ✗ ') . $label . "\n"; }
function throws(callable $f, string $class): bool { try { $f(); return false; } catch (Throwable $e) { return $e instanceof $class; } }

echo "Moteur d'enregistrement\n";
$s = new Store('entreprises');
check('import initial depuis data/entreprises.json', $s->seedIfEmpty() === true);
check('un second import ne réécrit rien', $s->seedIfEmpty() === false);
$r = $s->read(); $n0 = count($r['items']);
check("lecture : $n0 fiches, version calculée", $n0 > 50 && strlen($r['version']) === 16);

$items = $r['items']; $items[0]['phone'] = '01 02 03 04 05';
$v1 = $s->write($items, $r['version'], 'test@vib', 'Modif téléphone');
check('écriture acceptée sur la bonne version', $s->read()['items'][0]['phone'] === '01 02 03 04 05');
check('écriture sur une version périmée → REFUSÉE (conflit)', throws(fn() => $s->write($items, $r['version'], 'autre@vib', 'x'), StoreConflict::class));
check('liste vide refusée', throws(fn() => $s->write([], $v1, 'test@vib', 'x'), InvalidArgumentException::class));
$dup = $items; $dup[] = $items[0];
check('slug en double refusé', throws(fn() => $s->write($dup, $v1, 'test@vib', 'x'), InvalidArgumentException::class));
check('fiche sans slug refusée', throws(fn() => $s->write([['name' => 'x']], $v1, 'test@vib', 'x'), InvalidArgumentException::class));
check('objet au lieu d’une liste refusé', throws(fn() => $s->write(['a' => ['slug' => 'a']], $v1, 'test@vib', 'x'), InvalidArgumentException::class));

$h = $s->history();
check('historique : 2 versions (import + modif), la plus récente d’abord', count($h) === 2 && $h[0]['message'] === 'Modif téléphone' && $h[0]['author'] === 'test@vib');
$v2 = $s->restore($h[1]['id'], $v1, 'test@vib');
check('restauration de la version d’origine', $s->read()['items'][0]['phone'] !== '01 02 03 04 05' && count($s->read()['items']) === $n0);
check('la restauration est elle-même une version (annulable)', count($s->history()) === 3);
check('identifiant de version piégé refusé', throws(fn() => $s->historyItems('../../etc/passwd'), InvalidArgumentException::class));
check('collections séparées (locaux)', (new Store('locaux'))->seedIfEmpty() === true && count((new Store('locaux'))->read()['items']) >= 1);

$loc = new Store('locaux'); $raw = json_encode($loc->read()['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION);
check('nombres réécrits à l’identique (107.87, 179.0)', str_contains($raw, '107.87,') && !str_contains($raw, '107.8700') && str_contains($raw, '179.0'));
$seed = json_decode(file_get_contents(__DIR__ . '/../data/locaux.json'), true);
check('import fidèle : contenu identique au fichier d’origine', $loc->read()['items'] === $seed);

echo "\nÉcritures simultanées (8 processus × 10 ajouts, avec relecture en cas de conflit)\n";
$php = PHP_BINARY; $workers = 8; $per = 10; $procs = [];
$worker = __DIR__ . '/worker.php';
for ($w = 0; $w < $workers; $w++) $procs[] = proc_open([$php, $worker, $tmp, (string) $w, (string) $per], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes[$w]);
$conflicts = 0;
foreach ($procs as $w => $p) { $out = stream_get_contents($pipes[$w][1]); $err = stream_get_contents($pipes[$w][2]); proc_close($p); $conflicts += (int) $out; if ($err) echo "    worker $w : $err\n"; }
$final = (new Store('entreprises'))->read()['items'];
$added = count(array_filter($final, fn($e) => str_starts_with($e['slug'], 'test-w')));
check("aucune écriture perdue : $added ajouts sur " . ($workers * $per) . " ($conflicts conflits détectés puis rejoués)", $added === $workers * $per);
check('fichier final valide et sans doublon', count($final) === count(array_unique(array_column($final, 'slug'))));
check('historique complet : une version par écriture', count((new Store('entreprises'))->history(1000)) === 3 + $workers * $per);
check('aucun fichier temporaire oublié', !glob("$tmp/*.tmp-*") && !glob("$tmp/historique/*/*.tmp-*"));

echo "\nVérification Cloudflare Access (2e verrou)\n";
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$d = openssl_pkey_get_details($key);
$b64 = fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
$jwks = ['keys' => [['kid' => 'k1', 'kty' => 'RSA', 'n' => $b64($d['rsa']['n']), 'e' => $b64($d['rsa']['e'])]]];
$make = function (array $payload, string $kid = 'k1') use ($key, $b64) {
    $h = $b64(json_encode(['alg' => 'RS256', 'kid' => $kid, 'typ' => 'JWT'])); $p = $b64(json_encode($payload));
    openssl_sign("$h.$p", $sig, $key, OPENSSL_ALGO_SHA256); return "$h.$p." . $b64($sig);
};
$now = time();
$good = ['aud' => ['aud-test-123'], 'iss' => 'https://vib.cloudflareaccess.com', 'email' => 'Assist.Co@v-i-b.fr', 'iat' => $now, 'exp' => $now + 3600];
check('jeton valide → identité reconnue', vib_access_identity($make($good), $jwks, $now)['email'] === 'assist.co@v-i-b.fr');
check('badge technique (service token) reconnu', vib_access_identity($make(['common_name' => 'tests-claude'] + array_diff_key($good, ['email' => 1])), $jwks, $now)['kind'] === 'service');
check('sans jeton → refusé', throws(fn() => vib_access_identity('', $jwks, $now), AccessDenied::class));
$tok = $make($good); [$h, $p, $sg] = explode('.', $tok); $forged = $h . '.' . $b64(json_encode(['email' => 'pirate@x.fr'] + $good)) . '.' . $sg;
check('jeton falsifié (email modifié) → refusé', throws(fn() => vib_access_identity($forged, $jwks, $now), AccessDenied::class));
check('autre application (AUD) → refusé', throws(fn() => vib_access_identity($make(['aud' => ['autre']] + $good), $jwks, $now), AccessDenied::class));
check('autre équipe Cloudflare → refusé', throws(fn() => vib_access_identity($make(['iss' => 'https://pirate.cloudflareaccess.com'] + $good), $jwks, $now), AccessDenied::class));
check('jeton expiré → refusé', throws(fn() => vib_access_identity($make(['exp' => $now - 600] + $good), $jwks, $now), AccessDenied::class));
check('clé de signature inconnue → refusé', throws(fn() => vib_access_identity($make($good, 'k2'), $jwks, $now), AccessDenied::class));
$other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$hh = $b64(json_encode(['alg' => 'RS256', 'kid' => 'k1'])); $pp = $b64(json_encode($good)); openssl_sign("$hh.$pp", $bad, $other, OPENSSL_ALGO_SHA256);
check('signé par une autre clé → refusé', throws(fn() => vib_access_identity("$hh.$pp." . $b64($bad), $jwks, $now), AccessDenied::class));
check('algorithme « none » → refusé', throws(fn() => vib_access_identity($b64(json_encode(['alg' => 'none', 'kid' => 'k1'])) . ".$pp.", $jwks, $now), AccessDenied::class));

exec('rm -rf ' . escapeshellarg($tmp));
echo "\n" . ($ko === 0 ? "TOUS LES TESTS PASSENT ($ok)" : "ÉCHECS : $ko sur " . ($ok + $ko)) . "\n";
exit($ko === 0 ? 0 : 1);
