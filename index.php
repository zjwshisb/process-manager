<?php
require_once "vendor/autoload.php";


$m = new \Zjwshisb\ProcessManager\Manager();

$m->spawnPHP(function () {
  echo "haha". PHP_EOL;
}, 5,  10, 5)
    ->spawnCMD(["ls"], ["timeout" => 5],  10, 5);
$m->start();