<?php
declare(strict_types=1);
namespace Zjwshisb\ProcessManager\Exception;
use Symfony\Component\Process\Exception\RuntimeException;
use Zjwshisb\ProcessManager\Process\ProcessInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException as BaseProcessTimedOutException;

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

    /**
     * @return ProcessInterface
     */
    public function getProcess(): ProcessInterface
    {
        return $this->process;
    }

    /**
     * @return bool
     */
    public function isGeneralTimeout(): bool
    {
        return BaseProcessTimedOutException::TYPE_GENERAL === $this->timeoutType;
    }

    /**
     * @return bool
     */
    public function isIdleTimeout(): bool
    {
        return BaseProcessTimedOutException::TYPE_IDLE === $this->timeoutType;
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
