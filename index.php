<?php
require_once "vendor/autoload.php";


$m = new \Zjwshisb\ProcessManager\Manager();

$m->spawn(function () {
    echo "Test";
}, [
    "timeout" => 5
],  100);
$m->start();