<?php

namespace Zjwshisb\ProcessManager\Process\Traits;

use Symfony\Component\Process\Exception\LogicException;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

/**
 * @mixin ProcessInterface
 */
trait WithEndTime
{
    protected ?float $endTime = null;

    /**
     * set process terminate time
     */
    public function updateEndTime(): void
    {
        if (is_null($this->endTime)) {
            $this->endTime = microtime(true);
        }
    }

    /**
     * Get end time
     */
    public function getEndTime(): float
    {
        if (! $this->isTerminated() || $this->endTime === null) {
            throw new LogicException('End time is only available after process terminated.');
        }

        return $this->endTime;
    }

    public function getDurationTime(): float
    {
        if (! $this->isTerminated()) {
            throw new LogicException('duration time is only available after process terminated.');
        }

        return $this->getEndTime() - $this->getStartTime();
    }
}
