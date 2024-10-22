<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Process;

use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;

interface ProcessInterface
{
    /**
     * Start Process
     */
    public function start(): void;

    /**
     * Restart the process
     *
     * @return $this
     */
    public function restart(): static;

    /**
     * stop the process
     */
    public function stop(float $timeout = 10, ?int $signal = null): ?int;

    /**
     * process pid
     */
    public function getPid(): ?int;

    /**
     * Get Process start time.
     */
    public function getStartTime(): float;

    /**
     * Get Process end time.
     */
    public function getEndTime(): float;

    /**
     * Get Process end time.
     */
    public function updateEndTime(): void;

    /**
     * Get Process run time.
     */
    public function getDurationTime(): float;

    /**
     * @return array<string, mixed>
     */
    public function getInfo(bool $withExit = false): array;

    public function needRestart(): bool;

    public function getCurrentRunTimes(): int;

    /**
     * Get the process error output
     */
    public function getErrorOutput(): mixed;

    /**
     * Get the process successful output
     */
    public function getOutput(): mixed;

    /**
     * Check the Process is running out of time
     *
     * @throws ProcessTimedOutException
     */
    public function checkTimeout(): void;

    /**
     * Get the Process timeout
     */
    public function getTimeout(): ?float;

    public function setTimeout(float $timeout): static;

    /**
     * Get process exit code
     */
    public function getExitCode(): ?int;

    /**
     * Get process exit code text
     */
    public function getExitCodeText(): ?string;

    /**
     * Get the idle time
     * Only support ProcProcess
     * PcntlProcess always return null
     */
    public function getIdleTimeout(): ?float;

    /**
     * Get the command line
     * only support ProcProcess
     * PcntlProcess always return null;
     */
    public function getCommandLine(): ?string;

    /**
     * Get Process unique id.
     */
    public function getUid(): ?string;

    public function setUid(): void;

    /**
     * Process is run success or not
     */
    public function isSuccessful(): bool;

    /**
     * Process is running or not.
     */
    public function isRunning(): bool;

    /**
     * Process is Terminated or not.
     */
    public function isTerminated(): bool;

    /**
     * trigger success event handle
     */
    public function triggerSuccessListeners(): static;

    /**
     * trigger error event handle
     */
    public function triggerErrorListeners(): static;

    /**
     * trigger timeout event handle
     */
    public function triggerTimeoutListeners(): static;
}
