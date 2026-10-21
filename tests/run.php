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

// Fidélité de FORME, pas seulement de valeur : json_decode(..., false) distingue {} de []
$ent = new Store('entreprises'); $ent->restore($ent->history(100)[count($ent->history(100)) - 1]['id'], $ent->read()['version'], 'test@vib');
$seedE = json_decode(file_get_contents(__DIR__ . '/../data/entreprises.json'), false);
check('entreprises : contenu ET forme identiques au fichier d’origine ({} reste {})', json_decode($ent->publicJson(), false) == $seedE && !str_contains($ent->publicJson(), '"social": []'));
$it = $ent->read(); $w = $it['items']; $w[0]['social'] = []; $ent->write($w, $it['version'], 'test@vib', 'social vidé');
check('un « social » vidé dans le back-office est réécrit {} et non []', str_contains(file_get_contents("$tmp/entreprises.json"), '"social": {}') && !str_contains(file_get_contents("$tmp/entreprises.json"), '"social": []'));
$seedL = json_decode(file_get_contents(__DIR__ . '/../data/locaux.json'), false);
check('locaux : contenu ET forme identiques au fichier d’origine', json_decode($loc->publicJson(), false) == $seedL);

echo "\nModification fiche par fiche\n";
$e = new Store('entreprises'); $cur = $e->read()['items']; $A = $cur[1]; $B = $cur[2];
$hA = Store::itemHash($A); $hB = Store::itemHash($B);
$e->updateItem($A['slug'], $hA, fn($x) => ['phone' => '01 11 11 11 11'] + $x, 'alice@vib', 'modif A');
$okB = true; try { $e->updateItem($B['slug'], $hB, fn($x) => ['phone' => '02 22 22 22 22'] + $x, 'bob@vib', 'modif B'); } catch (Throwable $t) { $okB = false; }
check('deux personnes, deux fiches différentes → les DEUX enregistrements passent', $okB && array_column($e->read()['items'], 'phone', 'slug')[$A['slug']] === '01 11 11 11 11');
check('même fiche modifiée entre-temps → REFUSÉ', throws(fn() => $e->updateItem($A['slug'], $hA, fn($x) => ['phone' => 'x'] + $x, 'bob@vib', 'x'), StoreConflict::class));
check('fiche retirée entre-temps → REFUSÉ', throws(fn() => $e->updateItem('nexiste-pas', 'abc', fn($x) => $x, 'bob@vib', 'x'), StoreConflict::class));
check('changer l’identifiant d’une fiche par cette voie → refusé', throws(fn() => $e->updateItem($B['slug'], Store::itemHash(array_column($e->read()['items'], null, 'slug')[$B['slug']]), fn($x) => ['slug' => 'autre'] + $x, 'bob@vib', 'x'), InvalidArgumentException::class));
check('autres fiches intactes', count($e->read()['items']) === count($cur) && array_column($e->read()['items'], null, 'slug')[$cur[3]['slug']] === $cur[3]);

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
check('historique complet : une version par écriture', count((new Store('entreprises'))->history(1000)) === 7 + $workers * $per);
check('aucun fichier temporaire oublié', !glob("$tmp/*.tmp-*") && !glob("$tmp/historique/*/*.tmp-*"));

echo "\nRendu des horaires (fiches)\n";
require_once __DIR__ . '/../lib/render.php';
$lab = fn(string $t) => preg_match_all('#<strong>(.*?)</strong>#u', vib_render_hours($t), $m) ? implode(' | ', $m[1]) : '(aucun libellé)';
check('« Du mardi au samedi » → « Mardi - Samedi » en colonne', $lab('Du mardi au samedi de 7h30 à 13h et de 15h30 à 18h30') === 'Mardi - Samedi');
check('« Tous les jours » reconnu', $lab('Tous les jours de 11h30 à 14h30 et de 18h30 à 22h') === 'Tous les jours');
check('« Le samedi » → « Samedi »', $lab('Du mardi au vendredi de 12h à 14h · Le samedi de 19h à 21h30') === 'Mardi - Vendredi | Samedi');
check('« Le soir … » n’est pas pris pour un jour', $lab('Le soir sur réservation') === '(aucun libellé)');
check('liste non consécutive laissée telle quelle', $lab('Lundi - Mercredi - Vendredi de 9h à 12h') === 'Lundi - Mercredi - Vendredi');
check('3 jours consécutifs compactés', $lab('Lundi, mardi, mercredi de 9h à 12h') === 'Lundi - Mercredi');
check('plage insécable (« de 7h30 à 13h » dans un seul bloc)', str_contains(vib_render_hours('Mardi de 7h30 à 13h'), '<span class="hours-slot">de 7h30 à 13h</span>'));

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

echo "\nFormulaire du back-office (fiche acteur)\n";
require_once __DIR__ . '/../lib/fields.php';
$all = json_decode((string) file_get_contents(__DIR__ . '/../data/entreprises.json'), true);
$same = 0; foreach ($all as $a) { $r = vib_actor_apply($a, vib_actor_form_values($a)); if ($r['item'] === $a && !$r['changed'] && !$r['errors']) $same++; }
check("réenregistrer sans rien toucher laisse chaque fiche identique ($same / " . count($all) . ')', $same === count($all));
$base = ['slug' => 'x', 'name' => 'Ancien', 'category' => 'commerces', 'category_label' => 'Commerces', 'description' => 'D', 'address' => '',
         'phone' => '', 'email' => '', 'website' => '', 'hours' => 'Lundi de 9h à 12h', 'photos' => ['/a.jpg'], 'logo' => '/l.png',
         'social' => ['instagram' => 'https://instagram.com/a', 'facebook' => 'https://facebook.com/a'], 'champ_inconnu' => 1];
$form = vib_actor_form_values($base);
$r = vib_actor_apply($base, ['category' => 'sante', 'phone' => '0559239310', 'website' => 'www.exemple.fr', 'building' => 'Halle DALBA', 'street' => '5 rue des Mésanges', 'lot' => '9-10'] + $form);
check('catégorie → libellé mis à jour', $r['item']['category_label'] === 'Santé & soins');
check('téléphone remis au format « 05 59 23 93 10 »', $r['item']['phone'] === '05 59 23 93 10');
check('site sans https:// → complété', $r['item']['website'] === 'https://www.exemple.fr');
check('adresse composée au format client', $r['item']['address'] === 'Halle DALBA - 5 rue des Mésanges - Lots 9 et 10 - 64200 BIARRITZ');
check('horaires, photos, logo et champs inconnus intacts', [$r['item']['hours'], $r['item']['photos'], $r['item']['logo'], $r['item']['champ_inconnu']] === [$base['hours'], $base['photos'], $base['logo'], 1]);
check('historique : liste des champs modifiés', $r['changed'] === ['catégorie', 'adresse', 'téléphone', 'site web']);
$r = vib_actor_apply($base, ['social' => ['instagram' => '', 'facebook' => 'https://facebook.com/a', 'tiktok' => 'tiktok.com/@a']] + $form);
check('réseaux : vidé = retiré, ordre gardé, nouveau à la suite', $r['item']['social'] === ['facebook' => 'https://facebook.com/a', 'tiktok' => 'https://tiktok.com/@a']);
$r = vib_actor_apply($base, ['social' => []] + $form);
check('tous les réseaux vidés → enregistré en objet vide {}', str_contains((new Store('entreprises', $tmp))->encode([$r['item']]), '"social": {}'));
$r = vib_actor_apply($base, ['name' => '  ', 'email' => 'pas-un-mail', 'website' => 'javascript:alert(1)', 'category' => 'pirate'] + $form);
check('nom vide, e-mail, lien et catégorie invalides → refusés', array_keys($r['errors']) === ['name', 'category', 'email', 'website']);
check('lien social « javascript: » refusé', isset(vib_actor_apply($base, ['social' => ['facebook' => 'javascript:alert(1)']] + $form)['errors']['social']));
$r = vib_actor_apply($base, ['address_mode' => 'libre', 'address_free' => 'Ailleurs, 64200 BIARRITZ'] + $form);
check('adresse libre enregistrée telle quelle', $r['item']['address'] === 'Ailleurs, 64200 BIARRITZ');
check('numéro vert laissé tel quel', vib_phone_format('0 800 19 92 28') === '0 800 19 92 28');

$o = vib_actor_form_values($base);
$f = array_merge($o, ['website' => 'https://autre.fr', 'description' => 'Sa description']);
$m = vib_form_merge($o, array_merge($o, ['phone' => '05 59 00 00 01', 'description' => "Ma description\r\n"]), $f);
check('conflit : champ modifié par l’autre seul → sa valeur reprise', $m['values']['website'] === 'https://autre.fr');
check('conflit : champ modifié par moi seul → ma valeur gardée', $m['values']['phone'] === '05 59 00 00 01');
check('conflit : même champ modifié des deux côtés → signalé, ma valeur affichée', $m['conflicts'] === ['description' => 'Sa description'] && $m['values']['description'] === "Ma description\r\n");
$m = vib_form_merge($o, array_merge($o, ['social' => ['instagram' => '', 'facebook' => 'https://facebook.com/a']]), array_merge($o, ['social' => $o['social'] + ['tiktok' => 'https://tiktok.com/@a']]));
check('conflit : réseaux fusionnés un par un', $m['values']['social'] === ['facebook' => 'https://facebook.com/a', 'tiktok' => 'https://tiktok.com/@a'] && !$m['conflicts']);

echo "\nHoraires : grille ⇄ texte\n";
$ok87 = 0; $grid87 = 0;
foreach ($all as $a) {
    $p = vib_hours_parse($a['hours']);
    if ($p) { $grid87++; $q = vib_hours_parse(vib_hours_compose($p['grid'], $p['note'])); if ($q == $p) $ok87++; }
}
check("chaque horaire lisible en grille se réécrit à l’identique ($ok87 / $grid87)", $ok87 === $grid87 && $grid87 >= 70);
check('« 15h3à » (coquille) → texte libre, pas de devinette', vib_hours_parse('Du lundi au vendredi de 6h30 à 15h3à') === null);
check('« sur RDV » → texte libre (rien de perdu)', vib_hours_parse('Du mardi au jeudi de 10h à 12h · Du vendredi au samedi sur RDV') === null);
check('« Mardi - Jeudi » ambigu → texte libre', vib_hours_parse('Lundi - Mercredi - Vendredi de 9h à 18h · Mardi - Jeudi de 9h à 18h30') === null);
check('précision en fin de texte gardée', vib_hours_parse('Du lundi au samedi de 12h à 14h · Le soir sur réservation')['note'] === 'Le soir sur réservation');
$hb = vib_hours_form_values('');
$gridPost = function (array $days) use ($hb) { $h = $hb['h']; foreach ($days as $d => $v) $h[$d] = array_merge(['open' => '1', 's1' => '', 'e1' => '', 's2' => '', 'e2' => ''], $v); return ['hours_mode' => 'grille', 'h' => $h, 'hours_note' => '']; };
$wk = ['s1' => '09:00', 'e1' => '12:00', 's2' => '14:00', 'e2' => '18:00'];
check('grille → « Du lundi au vendredi de 9h à 12h et de 14h à 18h · Samedi de 9h à 12h »',
    vib_hours_apply('', $gridPost([0 => $wk, 1 => $wk, 2 => $wk, 3 => $wk, 4 => $wk, 5 => ['s1' => '09:00', 'e1' => '12:00']]))['text'] === 'Du lundi au vendredi de 9h à 12h et de 14h à 18h · Samedi de 9h à 12h');
check('après minuit et minuit : « de 18h à 2h », « de 20h à 24h »', vib_hours_apply('', $gridPost([4 => ['s1' => '18:00', 'e1' => '02:00'], 5 => ['s1' => '20:00', 'e1' => '00:00']]))['text'] === 'Vendredi de 18h à 2h · Samedi de 20h à 24h');
check('grille inchangée → texte d’origine gardé mot pour mot', vib_hours_apply('Lundi - Vendredi 08:00 - 19:00 · Dimanche Fermé', vib_hours_form_values('Lundi - Vendredi 08:00 - 19:00 · Dimanche Fermé'))['text'] === 'Lundi - Vendredi 08:00 - 19:00 · Dimanche Fermé');
check('jour coché sans heure → refusé', (bool) vib_hours_apply('', $gridPost([0 => []]))['error']);
check('2e plage avant la 1re → refusée', (bool) vib_hours_apply('', $gridPost([0 => ['s1' => '14:00', 'e1' => '18:00', 's2' => '09:00', 'e2' => '12:00']]))['error']);
check('aucun jour coché → refusé', (bool) vib_hours_apply('', $gridPost([]))['error']);
check('« Non renseignés » → vide ; « Sur rendez-vous »', vib_hours_apply('Lundi de 9h à 12h', ['hours_mode' => 'aucun'])['text'] === '' && vib_hours_apply('', ['hours_mode' => 'rdv'])['text'] === 'Sur rendez-vous');
check('texte libre : retours à la ligne → « · »', vib_hours_apply('', ['hours_mode' => 'libre', 'hours_free' => "Lundi de 9h à 12h\r\nMardi sur RDV"])['text'] === 'Lundi de 9h à 12h · Mardi sur RDV');

exec('rm -rf ' . escapeshellarg($tmp));
echo "\n" . ($ko === 0 ? "TOUS LES TESTS PASSENT ($ok)" : "ÉCHECS : $ko sur " . ($ok + $ko)) . "\n";
exit($ko === 0 ? 0 : 1);
