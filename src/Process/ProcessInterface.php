<?php
namespace Zjwshisb\ProcessManager\Process;

interface ProcessInterface
{
    /**
     * Start Process
     */
    public function start(): void;

    /**
     * Process is running or not
     */
    public function isRunning(): bool;

    /**
     * process pid
     */
    public function getPid(): ?int;



    public function getInfo(bool $withExit) : array;

    public function repeatable() : bool;

    /**
     * Get the process error output
     */
    public function getErrorOutput(): string;

    /**
     * Process is run success or not
     */
    public function isSuccessful() : bool;

    /**
     * Check the Process is running out of time
     */
    public function checkTimeout();

    /**
     * Restart the process
     * @return $this
     */
    public function restart(): static;

    public function getExitCode() : ?int;

    public function getExitCodeText() : ?string;

    public function getIdleTimeout(): ?float;

    public function getTimeout(): ?float;

    public function getCommandLine(): ?string;
}