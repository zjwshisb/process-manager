<?php
namespace Zjwshisb\ProcessManager\Exception;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process;
use Zjwshisb\ProcessManager\Process\ProcessInterface;

class ProcessTimedOutException extends SymfonyProcessTimedOutException
{
    private ProcessInterface $process;
    private int $timeoutType;

    public function __construct(ProcessInterface $process, int $timeoutType)
    {
        $this->process = $process;
        $this->timeoutType = $timeoutType;
        if ($process instanceof  Process ){
            parent::__construct($process, $timeoutType);
        } else {
            $this->message = sprintf(
                'The process "%s" exceeded the timeout of %s seconds.',
                $process->getCommandLine(),
                $this->getExceededTimeout()
            );
        }
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
        return self::TYPE_GENERAL === $this->timeoutType;
    }

    /**
     * @return bool
     */
    public function isIdleTimeout(): bool
    {
        return self::TYPE_IDLE === $this->timeoutType;
    }

    public function getExceededTimeout(): ?float
    {
        return match ($this->timeoutType) {
            self::TYPE_GENERAL => $this->process->getTimeout(),
            self::TYPE_IDLE => $this->process->getIdleTimeout(),
            default => throw new \LogicException(sprintf('Unknown timeout type "%d".', $this->timeoutType)),
        };
    }
}
