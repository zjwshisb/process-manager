<?php
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

    public function getStartTime(): float;

    public function getEndTime(): float;

    public function getDurationTime(): float;


    public function getInfo(bool $withExit) : array;

    public function repeatable() : bool;

    public function getRunCount() : int;

    /**
     * Get the process error output
     */
    public function getErrorOutput(): string;

    public function getOutput(): string;



    /**
     * Check the Process is running out of time
     */
    public function checkTimeout();



    public function getExitCode() : ?int;

    public function getExitCodeText() : ?string;

    public function getIdleTimeout(): ?float;

    public function getTimeout(): ?float;

    public function getCommandLine(): ?string;

    public function getUuid(): ?string;

    /**
     * Process is run success or not
     */
    public function isSuccessful() : bool;
    public function isRunning(): bool;

    public function isTerminated(): bool;
}