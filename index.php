<?php
require_once "vendor/autoload.php";

$m = new \Zjwshisb\ProcessManager\Manager();
$m->spawnPhp(function () {
    $sleep = rand(1, 10);
    sleep($sleep);
    return [1,2];
})->onSuccess(function (\Zjwshisb\ProcessManager\Process\PcntlProcess $process, $result) {
    echo "111" . PHP_EOL;
})->setProcessCount(10)->setRunTimes(10)
    ->setTimeout(11);

$m->start();