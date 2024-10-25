<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\LogicException;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
use Zjwshisb\ProcessManager\Process\PcntlProcess;
use Zjwshisb\ProcessManager\Process\ProcessInterface;
use Zjwshisb\ProcessManager\Process\ProcProcess;

class Manager
{
    protected string $name;

    protected string $runtime;

    protected string $status = self::STATUS_READY;

    /**
     * processes which is running
     *
     * @var array<ProcessInterface>
     */
    protected array $runningProcesses = [];

    /**
     * processes which is running
     *
     * @var array<ProcessInterface>
     */
    protected array $restartProcesses = [];

    /**
     * processes waiting to start
     *
     * @var array<Job>
     */
    protected array $jobs = [];

    protected ?LoggerInterface $logger = null;

    protected int $sleepTime;

    const STATUS_READY = 'ready';

    const STATUS_RUNNING = 'running';

    const STATUS_EXITING = 'exiting';

    /**
     * @param  string  $name  identified name
     * @param  string  $runtime  dir to save log
     * @param  int  $sleepTime  sleep time in microseconds
     */
    public function __construct(string $name = 'php process manager', string $runtime = '', int $sleepTime = 100000)
    {
        $this->name = $name;
        $this->runtime = $runtime;
        $this->sleepTime = $sleepTime;
        cli_set_process_title($this->name);
    }

    /**
     * @return $this
     */
    public function setLogger(?LoggerInterface $logger = null): static
    {
        if (! $logger) {
            $logger = new Logger($this->name);
            $logger->pushHandler(new StreamHandler($this->runtime.'/process.log', Level::Info));
        }
        $this->logger = $logger;

        return $this;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Add CMD Job
     *
     * @param  array<string>  $cmd
     */
    public function spawnCmd(array $cmd): ProcJob
    {
        $job = new ProcJob(new ProcProcess($cmd));
        $this->jobs[] = $job;

        return $job;
    }

    /**
     * Add PHP Process
     */
    public function spawnPhp(callable $callback): PcntlJob
    {
        if (! is_callable($callback)) {
            throw new LogicException('Params callback must can be called as a function');
        }
        $job = new PcntlJob(new PcntlProcess($callback));
        $this->jobs[] = $job;

        return $job;
    }

    /**
     * @return $this
     */
    protected function addRestartProcess(ProcessInterface $process): static
    {
        if ($process->needRestart() && $this->status === self::STATUS_RUNNING) {
            $this->restartProcesses[] = $process;
        }

        return $this;
    }

    /**
     *  Process success callback
     */
    protected function handleProcessSuccess(ProcessInterface $process): void
    {
        $this->logger?->info($this->getProcessTag($process, 'Down'), $process->getInfo(true));
        $process->triggerSuccessListeners();
        $this->addRestartProcess($process);
    }

    /**
     * Process timeout callback
     */
    protected function handleProcessTimeout(ProcessInterface $process): void
    {
        $this->logger?->info($this->getProcessTag($process, 'Timeout'), $process->getInfo(true));
        $process->triggerTimeoutListeners();
        $this->addRestartProcess($process);
    }

    /**
     * Handle error process
     */
    protected function handleProcessError(ProcessInterface $process): void
    {
        $this->logger?->error(
            $this->getProcessTag($process, 'Error'),
            array_merge(
                $process->getInfo(true),
                [
                    'error' => $process->getErrorOutput(),
                ]
            )
        );
        $process->triggerErrorListeners();
        $this->addRestartProcess($process);
    }

    /**
     * Manager end
     */
    protected function end(): void
    {
        $this->logger?->info('End Manager');
    }

    public function exit(int $signal = 0): void
    {
        $this->status = self::STATUS_EXITING;
        $this->stopProcesses();
        echo 'exit by signal '.$signal.PHP_EOL;
    }

    /**
     * Start processes
     *
     * @return $this
     */
    protected function startProcesses(): static
    {
        foreach ($this->jobs as $job) {
            foreach ($job as $process) {
                $process->start();
                $this->runningProcesses[] = $process;
                $this->logger?->info($this->getProcessTag($process, 'start'), $process->getInfo());
            }
        }

        return $this;
    }

    /**
     * Restart processes
     *
     * @return $this
     */
    protected function restartProcesses(): static
    {
        foreach ($this->restartProcesses as $process) {
            $process = $process->restart();
            $pid = $process->getPid();
            $this->runningProcesses[$pid] = $process;
            $this->logger?->info($this->getProcessTag($process, 'restart'), $process->getInfo());
        }
        $this->restartProcesses = [];

        return $this;
    }

    /**
     * Stop all processes
     */
    public function stopProcesses(): void
    {
        foreach ($this->runningProcesses as $process) {
            $process->stop();
        }
    }

    /**
     * Register Signal Handler
     */
    protected function registerSignalHandler(): void
    {
        pcntl_signal(SIGTERM, [$this, 'exit']);
        pcntl_signal(SIGINT, [$this, 'exit']);
        pcntl_signal(SIGTSTP, [$this, 'exit']);
    }

    protected function getProcessTag(ProcessInterface $process, string $action): string
    {
        return sprintf('Process[%s][%d] %s', $process->getUid(), $process->getCurrentRunTimes(), $action);
    }

    /**
     * Start Manager
     */
    public function start(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->logger?->info('Start Manager');
        $this->registerSignalHandler();
        $this->startProcesses();
        while (true) {
            $this->runningProcesses = array_filter($this->runningProcesses, function (ProcessInterface $process) {
                if (! $process->isRunning()) {
                    $process->updateEndTime();
                    if ($process->isSuccessful()) {
                        $this->handleProcessSuccess($process);
                    } else {
                        $this->handleProcessError($process);
                    }

                    return false;
                } else {
                    try {
                        $process->checkTimeout();
                    } catch (ProcessTimedOutException $exception) {
                        $process->updateEndTime();
                        $this->handleProcessTimeout($process);

                        return false;
                    }
                }

                return true;
            });
            if (count($this->restartProcesses) > 0) {
                $this->restartProcesses();
            }
            if (count($this->runningProcesses) === 0) {
                break;
            }
            pcntl_signal_dispatch();
            usleep($this->sleepTime);
        }
        $this->end();
    }
}
