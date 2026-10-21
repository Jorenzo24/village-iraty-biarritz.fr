<?php
/** Processus d'écriture concurrente pour tests/run.php : ajoute N fiches, en relisant à chaque conflit. */
declare(strict_types=1);
[, $dir, $w, $n] = $argv;
putenv("VIB_DATA_DIR=$dir");
require __DIR__ . '/../lib/store.php';
$s = new Store('entreprises');
$conflicts = 0;
for ($i = 0; $i < (int) $n; $i++) {
    for ($try = 0; $try < 200; $try++) {
        $r = $s->read();
        $items = $r['items'];
        $items[] = ['slug' => "test-w$w-$i", 'name' => "Test $w-$i"];
        usleep(random_int(0, 3000));   // élargit la fenêtre entre lecture et écriture
        try { $s->write($items, $r['version'], "worker$w", "ajout $i"); break; }
        catch (StoreConflict) { $conflicts++; }
    }
}
echo $conflicts;
