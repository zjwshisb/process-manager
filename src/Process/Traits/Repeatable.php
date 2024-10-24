<?php

namespace Zjwshisb\ProcessManager\Process\Traits;

trait Repeatable
{
    protected int $runTimes = 1;

    protected int $currentRunTimes = 0;

    protected function addRunTimes(int $times = 1): static
    {
        $this->currentRunTimes += $times;

        return $this;
    }

    /**
     * Get the current run count
     */
    public function getCurrentRunTimes(): int
    {
        return $this->currentRunTimes;
    }

    /**
     * @param  int  $runTimes  times for process to run
     *                         negative or zero mean infinite
     * @return $this
     */
    public function setRunTimes(int $runTimes): static
    {
        $this->runTimes = $runTimes;

        return $this;
    }

    /**
     * whether process need to restart
     */
    public function needRestart(): bool
    {
        if ($this->runTimes <= 0) {
            return true;
        }

        return $this->getCurrentRunTimes() < $this->runTimes;
    }
}
