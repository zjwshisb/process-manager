<?php

use Zjwshisb\ProcessManager\Manager;

require_once 'vendor/autoload.php';

$m = new Manager;

$m->spawnPhp(function () {
    echo 1;
    sleep(1);
})->setRunTimes(-1);
$m->start();
