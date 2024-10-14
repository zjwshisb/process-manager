<?php
namespace Zjwshisb\ProcessManager\Traits;

trait Repeatable {
    protected bool|int $repetitive = false;
    protected int $currentRunCount = 0;

    protected function addRunCount(int $count = 1): static
    {
        $this->currentRunCount += $count;
        return $this;
    }

    public function getRunCount(): int
    {
        return $this->currentRunCount;
    }

    public function setRepeatable(bool|int $autoRestart) : static {
        $this->repetitive = $autoRestart;
        return $this;
    }

    public function repeatable() : bool
    {
        if ($this->repetitive === true) {
            return true;
        }
        if (is_int($this->repetitive)) {
            return  $this->currentRunCount < $this->repetitive;
        }
        return false;
    }


}