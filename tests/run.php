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

echo "\nAjout, retrait, historique d’une fiche\n";
$sd = $tmp . '/ajout';
$st = new Store('entreprises', $sd); $st->seedIfEmpty();
$n0 = count($st->read()['items']);
$taken = array_column($st->read()['items'], 'slug');
$new = vib_actor_new(vib_unique_slug('Café du Port', $taken), 'Café du Port', 'restaurants');
$st->addItem($new, 'a@x', 'Ajout');
check('ajout : fiche créée avec un identifiant propre', vib_find($st->read()['items'], 'cafe-du-port') !== null && count($st->read()['items']) === $n0 + 1);
check('ajout : identifiant déjà pris → refusé', throws(fn() => $st->addItem($new, 'a@x', 'Ajout'), StoreConflict::class));
check('ajout : forme exacte d’une fiche (social = {})', str_contains($st->encode([$new]), '"social": {}') && array_keys($new) === array_keys($all[0]));
$it = vib_find($st->read()['items'], 'veloff');
check('retrait : empreinte périmée → refusé', throws(fn() => $st->retireItem('veloff', 'perime', 'a@x'), StoreConflict::class));
$st->retireItem('veloff', Store::itemHash($it), 'a@x');
check('retrait : fiche hors du site, gardée dans les retirées', vib_find($st->read()['items'], 'veloff') === null && ($st->retired()[0]['item'] ?? null) === $it);
check('retrait : identifiant toujours réservé', in_array('veloff', vib_taken_slugs($st), true));
check('identifiants redirigés par le .htaccess réservés (b-ocean)', in_array('b-ocean', vib_taken_slugs($st), true));
$st->addItem($it, 'a@x', 'Réintégration');
check('réintégration : fiche identique, sortie des retirées', vib_find($st->read()['items'], 'veloff') === $it && $st->retired() === []);
$h0 = Store::itemHash($it);
$st->updateItem('veloff', $h0, fn($c) => ['phone' => '01 00 00 00 00'] + $c, 'a@x', 'Fiche « VELOFF » (veloff) : téléphone');
$st->updateItem('tunstall', Store::itemHash(vib_find($st->read()['items'], 'tunstall')), fn($c) => ['phone' => '02'] + $c, 'a@x', 'autre fiche');
$hist = $st->itemHistory('veloff');
check('historique d’une fiche : seulement ses propres changements', count($hist) === 4 && $hist[0]['item']['phone'] === '01 00 00 00 00' && $hist[1]['item'] === $it && $hist[2]['item'] === null);
check('historique : la version de départ y figure', ($hist[3]['entry']['base'] ?? 'x') === '' && $hist[3]['item'] === $it);

echo "\nPhotos de la fiche\n";
$baseP = $base;
$r = vib_actor_apply($baseP, ['photos' => ['', '/a.jpg']] + $form);
check('photo existante gardée, champ vide ignoré', $r['item']['photos'] === ['/a.jpg'] && !$r['errors']);
$r = vib_actor_apply($baseP, ['photos' => ['/etc/passwd']] + $form);
check('chemin arbitraire refusé', isset($r['errors']['photos']));
$r = vib_actor_apply($baseP, ['photos' => ['/uploads/entreprises/autre/photo-20261021-120000-abcdef.jpg']] + $form);
check('photo d’une autre fiche refusée', isset($r['errors']['photos']));
$r = vib_actor_apply($baseP, ['photos' => [], 'logo' => ''] + $form);
check('toutes les photos et le logo retirés', $r['item']['photos'] === [] && $r['item']['logo'] === '' && $r['changed'] === ['photos', 'logo']);

echo "\nImages envoyées\n";
require_once __DIR__ . '/../lib/images.php';
$jp = $tmp . '/o.jpg';
$im = imagecreatetruecolor(400, 300); imagejpeg($im, $jp);
check('JPEG sans EXIF → orientation normale', vib_jpeg_orientation($jp) === 1);
// EXIF minimal « Orientation = 6 » (photo de téléphone tenue droite), inséré après le marqueur SOI
foreach (['II' => 'v', 'MM' => 'n'] as $bo => $f16) {
    $f32 = $bo === 'II' ? 'V' : 'N';
    $tiff = $bo . pack($f16, 42) . pack($f32, 8) . pack($f16, 1) . pack($f16, 0x0112) . pack($f16, 3) . pack($f32, 1) . pack($f16, 6) . "\0\0" . pack($f32, 0);
    $app1 = "\xFF\xE1" . pack('n', strlen($tiff) + 8) . "Exif\0\0" . $tiff;
    $raw = (string) file_get_contents($jp);
    file_put_contents($tmp . "/o6$bo.jpg", substr($raw, 0, 2) . $app1 . substr($raw, 2));
    check("orientation EXIF lue sans l’extension exif ($bo)", vib_jpeg_orientation($tmp . "/o6$bo.jpg") === 6);
}
file_put_contents($tmp . '/tronque.jpg', "\xFF\xD8\xFF\xE1\x00");
check('JPEG tronqué → pas d’erreur, orientation normale', vib_jpeg_orientation($tmp . '/tronque.jpg') === 1);

echo "\nLocaux à louer\n";
require_once __DIR__ . '/../lib/fields-locaux.php';
$locs = json_decode((string) file_get_contents(__DIR__ . '/../data/locaux.json'), true);
$same = 0; foreach ($locs as $l) { $r = vib_local_apply($l, vib_local_form_values($l)); if ($r['item'] === $l && !$r['changed'] && !$r['errors']) $same++; }
check("réenregistrer sans rien toucher laisse chaque local identique ($same / " . count($locs) . ')', $same === count($locs));
$l0 = $locs[0]; $f0 = vib_local_form_values($l0);
check('saisie française : 1165.0 → « 1165 », 150.19 → « 150,19 »', $f0['price_ht'] === '1165' && $f0['charges_ht'] === '150,19');
$r = vib_local_apply($l0, ['price_ht' => '1 200,50', 'surface' => '95', 'norm_pmr' => ''] + $f0);
check('loyer « 1 200,50 » → 1200.5 ; surface 95 (entier) ; case décochée → false', $r['item']['price_ht'] === 1200.5 && $r['item']['surface'] === 95 && $r['item']['norm_pmr'] === false && $r['changed'] === ['surface', 'loyer', 'accès PMR']);
$r = vib_local_apply($l0, ['charges_ht' => ''] + $f0);
check('charges vides → 0 (bloc masqué sur la fiche)', $r['item']['charges_ht'] === 0.0);
$r = vib_local_apply($l0, ['price_ht' => 'mille', 'surface' => '0', 'name' => ''] + $f0);
check('loyer illisible, surface nulle, titre vide → refusés', array_keys($r['errors']) === ['name', 'surface', 'price_ht']);
$m = vib_local_merge($f0, array_merge($f0, ['price_ht' => '1200']), array_merge($f0, ['description' => 'Autre texte']));
check('conflit : loyer (moi) et description (l’autre) fusionnés', $m['values']['price_ht'] === '1200' && $m['values']['description'] === 'Autre texte' && !$m['conflicts']);
$sl = new Store('locaux', $tmp . '/locaux'); $sl->seedIfEmpty();
$new = vib_local_new(vib_unique_slug('Lot 9 - Bureau 30 m²', vib_taken_slugs($sl, 'local')), 'Lot 9 - Bureau 30 m²', 30, 600);
$sl->addItem($new, 'a@x', 'Ajout');
check('nouveau local : identifiant propre, forme exacte', $new['slug'] === 'lot-9-bureau-30-m2' && array_keys($new) === array_keys($locs[0]) && vib_local_apply($new, vib_local_form_values($new))['changed'] === []);
check('identifiant redirigé par le .htaccess réservé (local loué en août)', in_array('duplex-commercial-de-90mm2-ref-dar41', vib_taken_slugs($sl, 'local'), true));

echo "\nCartes des listes publiques\n";
require_once __DIR__ . '/../lib/cards.php';
check('badge : « Du lundi au vendredi de 9h à 12h et de 14h à 18h » → Mo…Fr 09:00-12:00,14:00-18:00',
    vib_hours_osm('Du lundi au vendredi de 9h à 12h et de 14h à 18h') === 'Mo 09:00-12:00,14:00-18:00;Tu 09:00-12:00,14:00-18:00;We 09:00-12:00,14:00-18:00;Th 09:00-12:00,14:00-18:00;Fr 09:00-12:00,14:00-18:00');
check('badge : après minuit, jours en boucle (dimanche → jeudi)', vib_hours_osm('Du dimanche au jeudi de 20h à 4h') === 'Mo 20:00-04:00;Tu 20:00-04:00;We 20:00-04:00;Th 20:00-04:00;Su 20:00-04:00');
check('badge : 24h/24, comme la carte d’origine', str_starts_with(vib_hours_osm('Du lundi au dimanche 24h/24h'), 'Mo 00:00-23:59;'));
check('badge : sur rendez-vous, sans heures, vide → pas de badge', vib_hours_osm('Sur rendez-vous') === '' && vib_hours_osm('Du lundi au vendredi') === '' && vib_hours_osm('') === '');
check('badge : « Dimanche Fermé » ignoré', vib_hours_osm('Samedi de 9h à 12h · Dimanche Fermé') === 'Sa 09:00-12:00');
$okOsm = 0; $withH = 0;
foreach ($all as $a) { $p = vib_hours_parse($a['hours']); if (!$p) continue; $withH++; $g = []; foreach ($p['grid'] as $d => $sl) foreach ($sl as [$x, $y]) $g[] = [$d, $x, $y === 1440 ? 1440 : $y]; $l = vib_hours_intervals($a['hours']) ?? []; sort($g); sort($l); if ($l === $g) $okOsm++; }
check("badge de carte = horaires de la fiche ($okOsm / $withH fiches en grille)", $okOsm === $withH);
check('accroche automatique : coupée sur un mot, ≈ 100 caractères', vib_card_excerpt_auto(str_repeat('mot ', 40)) === rtrim(str_repeat('mot ', 25)) . '…' && vib_card_excerpt_auto('Court.') === 'Court.');
check('initiales : « ASSOCIATION BLAK » → « AB »', vib_initials('ASSOCIATION BLAK') === 'AB');
$card = vib_card_actor(['slug' => 'x', 'name' => 'Café <b>', 'category' => 'restaurants', 'category_label' => 'Restaurants & Bars', 'description' => 'D', 'hours' => '', 'photos' => [], 'card_desc' => '']);
check('carte : nom échappé, pas de badge sans horaires, initiales sans photo', str_contains($card, 'Café &lt;b&gt;') && !str_contains($card, 'data-hours') && str_contains($card, 'acteur-card__ph'));
check('prix de carte : 1165.0 → « 1 165 € », 906.5 → « 906,50 € »', vib_card_price(1165.0) === "1\u{00A0}165\u{00A0}€" && vib_card_price(906.5) === "906,50\u{00A0}€");

echo "\nMise à jour du format du coffre\n";
$sm = new Store('entreprises', $tmp . '/migr'); $sm->seedIfEmpty();
$r0 = $sm->read();
$stripped = array_map(function ($it) { unset($it['card_desc']); return $it; }, $r0['items']);
$stripped[0]['name'] = 'Nom modifié entre-temps';
$sm->write($stripped, $r0['version'], 'a@x', 'coffre d’avant la mise à jour');
check('coffre sans le nouveau champ → complété', $sm->migrate() === true);
$items = $sm->read()['items'];
check('valeur reprise du fichier du dépôt pour la même fiche', $items[1]['card_desc'] === $all[1]['card_desc'] && $items[1]['card_desc'] !== '');
check('autres champs non touchés (modification intermédiaire gardée)', $items[0]['name'] === 'Nom modifié entre-temps');
check('une seule fois : ne refait rien ensuite', $sm->migrate() === false);
check('tracée dans l’historique', str_starts_with($sm->history(1)[0]['message'], 'Mise à jour du format'));

require_once __DIR__ . '/../lib/collections.php';
echo "\nDépublier, republier, supprimer\n";
$sd2 = new Store('locaux', $tmp . '/suppr'); $sd2->seedIfEmpty();
$l50 = vib_find($sd2->read()['items'], 'lot-50-darla-90m2');
$sd2->retireItem('lot-50-darla-90m2', Store::itemHash($l50), 'a@x');
check('dépubliée : listée parmi les dépubliées', count($sd2->retired(false)) === 1);
check('supprimer une fiche encore publiée → refusé', throws(fn() => $sd2->deleteRetired('lot-21-aldades-42m2', 'a@x'), StoreConflict::class));
$sd2->deleteRetired('lot-50-darla-90m2', 'a@x');
check('supprimée : n’apparaît plus parmi les dépubliées', $sd2->retired(false) === []);
check('supprimée : adresse toujours redirigée, identifiant toujours réservé', ($sd2->retired()[0]['item']['slug'] ?? '') === 'lot-50-darla-90m2' && in_array('lot-50-darla-90m2', vib_taken_slugs($sd2, 'local'), true));
check('supprimer deux fois → refusé', throws(fn() => $sd2->deleteRetired('lot-50-darla-90m2', 'a@x'), StoreConflict::class));
check('journal : « Supprimée définitivement »', vib_log_describe($sd2->history(1)[0])['label'] === 'Supprimée définitivement');

exec('rm -rf ' . escapeshellarg($tmp));
echo "\n" . ($ko === 0 ? "TOUS LES TESTS PASSENT ($ok)" : "ÉCHECS : $ko sur " . ($ok + $ko)) . "\n";
exit($ko === 0 ? 0 : 1);
