<?php
namespace Zjwshisb\ProcessManager\Group;

use Zjwshisb\ProcessManager\Process\ProcessInterface;


interface ProcessGroupInterface extends \IteratorAggregate{
    public function add(ProcessInterface $process): static;
}