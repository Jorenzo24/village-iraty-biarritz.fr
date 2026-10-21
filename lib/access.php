<?php
/**
 * Vérification côté serveur de Cloudflare Access (2e verrou du back-office).
 *
 * Cloudflare Access bloque déjà les visiteurs non autorisés. Mais l'IP du serveur est publique :
 * une requête envoyée directement au serveur, sans passer par Cloudflare, contournerait ce premier
 * verrou. Chaque page du back-office vérifie donc le jeton signé (JWT) que Cloudflare ajoute
 * à chaque requête autorisée (en-tête Cf-Access-Jwt-Assertion) :
 *   signature RS256 avec les clés publiques de l'équipe, audience (AUD) de l'application,
 *   émetteur, dates de validité.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

final class AccessDenied extends RuntimeException {}

/**
 * @return array{email: string, kind: string} identité vérifiée (kind = 'user' ou 'service')
 * @throws AccessDenied
 */
function vib_access_identity(?string $jwt = null, ?array $jwks = null, ?int $now = null): array
{
    $cfg = vib_config()['access'];
    if ($cfg['team_domain'] === '' || $cfg['aud'] === '') throw new AccessDenied('Cloudflare Access n’est pas configuré');
    $jwt ??= $_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'] ?? '';
    $now ??= time();
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) throw new AccessDenied('Jeton absent ou mal formé');
    [$h64, $p64, $s64] = $parts;
    $header = json_decode(vib_b64url($h64), true);
    $payload = json_decode(vib_b64url($p64), true);
    $sig = vib_b64url($s64);
    if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'RS256') throw new AccessDenied('Jeton invalide');

    $jwks ??= vib_access_keys($cfg['team_domain']);
    $key = null;
    foreach ($jwks['keys'] ?? [] as $k) if (($k['kid'] ?? null) === ($header['kid'] ?? '')) $key = $k;
    if (!$key) throw new AccessDenied('Clé de signature inconnue');
    $pem = vib_jwk_to_pem($key['n'], $key['e']);
    if (openssl_verify("$h64.$p64", $sig, $pem, OPENSSL_ALGO_SHA256) !== 1) throw new AccessDenied('Signature invalide');

    $aud = (array) ($payload['aud'] ?? []);
    if (!in_array($cfg['aud'], $aud, true)) throw new AccessDenied('Jeton destiné à une autre application');
    if (rtrim((string) ($payload['iss'] ?? ''), '/') !== rtrim($cfg['team_domain'], '/')) throw new AccessDenied('Émetteur inconnu');
    if (($payload['exp'] ?? 0) < $now - 30 || ($payload['nbf'] ?? $payload['iat'] ?? 0) > $now + 30) throw new AccessDenied('Jeton expiré');

    if (!empty($payload['email'])) return ['email' => strtolower((string) $payload['email']), 'kind' => 'user'];
    if (!empty($payload['common_name'])) return ['email' => 'service:' . $payload['common_name'], 'kind' => 'service'];
    throw new AccessDenied('Identité absente du jeton');
}

/** Clés publiques de l'équipe Cloudflare, mises en cache 1 h dans le dossier de données. */
function vib_access_keys(string $teamDomain): array
{
    $cache = vib_config()['data_dir'] . '/cache/access-certs.json';
    if (is_file($cache) && filemtime($cache) > time() - 3600) {
        $j = json_decode((string) file_get_contents($cache), true);
        if (is_array($j) && !empty($j['keys'])) return $j;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    $raw = @file_get_contents(rtrim($teamDomain, '/') . '/cdn-cgi/access/certs', false, $ctx);
    $j = json_decode((string) $raw, true);
    if (!is_array($j) || empty($j['keys'])) throw new AccessDenied('Clés Cloudflare indisponibles');
    @mkdir(dirname($cache), 0750, true);
    @file_put_contents($cache, $raw);
    return $j;
}

function vib_b64url(string $s): string
{
    $d = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    if ($d === false) throw new AccessDenied('Encodage invalide');
    return $d;
}

/** Convertit une clé RSA publique JWK (n, e) en PEM utilisable par openssl_verify. */
function vib_jwk_to_pem(string $n, string $e): string
{
    $der = fn(int $tag, string $v) => chr($tag) . vib_der_len(strlen($v)) . $v;
    $int = function (string $b) use ($der) { if (ord($b[0]) > 0x7f) $b = "\0" . $b; return $der(0x02, $b); };
    $rsa = $der(0x30, $int(vib_b64url($n)) . $int(vib_b64url($e)));
    $algo = $der(0x30, $der(0x06, "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00");
    $spki = $der(0x30, $algo . $der(0x03, "\0" . $rsa));
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function vib_der_len(int $len): string
{
    if ($len < 0x80) return chr($len);
    $b = ltrim(pack('N', $len), "\0");
    return chr(0x80 | strlen($b)) . $b;
}
