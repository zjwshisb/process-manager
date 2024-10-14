<?php
namespace Zjwshisb\ProcessManager\Traits;

use Symfony\Component\Process\Exception\LogicException;

trait WithEndTime {
    protected ?float $endTime = null;

    public function updateEndTime(): void
    {
        if (is_null($this->endTime)) {
            $this->endTime = microtime(true);
        }
    }

    public function getEndTime(): float
    {
        if ($this->isTerminated()) {
            throw new LogicException("End time is only available after process terminated.");
        }
        return $this->endTime;
    }

    public function getDurationTime(): float
    {
        if ($this->isTerminated()) {
            throw new LogicException("duration time is only available after process terminated.");
        }
        return $this->getEndTime() - $this->getStartTime();
    }
}