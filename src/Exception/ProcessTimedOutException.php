<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Exception;

use Symfony\Component\Process\Exception\ProcessTimedOutException as BaseProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

class ProcessTimedOutException extends RuntimeException
{
    private ProcessInterface $process;

    private int $timeoutType;

    public function __construct(ProcessInterface $process, int $timeoutType)
    {
        $this->process = $process;
        $this->timeoutType = $timeoutType;

        parent::__construct(sprintf(
            'The process "%s" exceeded the timeout of %s seconds.',
            $process->getCommandLine(),
            $this->getExceededTimeout()
        ));
    }

    public function getProcess(): ProcessInterface
    {
        return $this->process;
    }

    public function isGeneralTimeout(): bool
    {
        return $this->timeoutType === BaseProcessTimedOutException::TYPE_GENERAL;
    }

    public function isIdleTimeout(): bool
    {
        return $this->timeoutType === BaseProcessTimedOutException::TYPE_IDLE;
    }

    public function getExceededTimeout(): ?float
    {
        return match ($this->timeoutType) {
            BaseProcessTimedOutException::TYPE_GENERAL => $this->process->getTimeout(),
            BaseProcessTimedOutException::TYPE_IDLE => $this->process->getIdleTimeout(),
            default => throw new \LogicException(sprintf('Unknown timeout type "%d".', $this->timeoutType)),
        };
    }
}
