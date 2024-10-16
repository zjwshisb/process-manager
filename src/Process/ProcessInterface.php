<?php
declare(strict_types=1);
namespace Zjwshisb\ProcessManager\Process;

interface ProcessInterface
{
    /**
     * Start Process
     */
    public function start(): void;

    /**
     * Restart the process
     * @return $this
     */
    public function restart(): static;

    /**
     * process pid
     */
    public function getPid(): ?int;

    /**
     * Get Process start time.
     * @return float
     */
    public function getStartTime(): float;

    /**
     * Get Process end time.
     * @return float
     */
    public function getEndTime(): float;

    /**
     * Get Process run time.
     * @return float
     */
    public function getDurationTime(): float;

    /**
     * @param bool $withExit
     * @return array
     */
    public function getInfo(bool $withExit) : array;

    /**
     * @return bool
     */
    public function needRestart() : bool;

    /**
     * @return int
     */
    public function getCurrentRunTimes() : int;

    /**
     * Get the process error output
     */
    public function getErrorOutput(): string;

    /**
     * Get the process successful output
     * @return string
     */
    public function getOutput(): mixed;


    /**
     * Check the Process is running out of time
     */
    public function checkTimeout();

    /**
     * Get the Process timeout
     * @return float|null
     */
    public function getTimeout(): ?float;

    /**
     * @param float $timeout
     * @return mixed
     */
    public function setTimeout(float $timeout): static;

    /**
     * Get process exit code
     * @return int|null
     */
    public function getExitCode() : ?int;

    /**
     * Get process exit code text
     * @return string|null
     */
    public function getExitCodeText() : ?string;

    /**
     * Get the idle time
     * Only support ProcProcess
     * PcntlProcess always return null
     * @return float|null
     */
    public function getIdleTimeout(): ?float;


    /**
     * Get the command line
     * only support ProcProcess
     * PcntlProcess always return null;
     * @return string|null
     */
    public function getCommandLine(): ?string;

    /**
     * Get Process unique id.
     * @return string|null
     */
    public function getUid(): ?string;


    /**
     * @return void
     */
    public function setUid(): void;

    /**
     * Process is run success or not
     */
    public function isSuccessful() : bool;

    /**
     * Process is running or not.
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Process is Terminated or not.
     * @return bool
     */
    public function isTerminated(): bool;

    /**
     * trigger success event handle
     */
    public function triggerSuccessEvent();

    /**
     * trigger error event handle
     */
    public function triggerErrorEvent();

    /**
     * trigger timeout event handle
     */
    public function triggerTimeoutEvent();
}