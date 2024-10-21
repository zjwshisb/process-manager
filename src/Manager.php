<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Exception\LogicException;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
use Zjwshisb\ProcessManager\Process\PcntlProcess;
use Zjwshisb\ProcessManager\Process\ProcessInterface;
use Zjwshisb\ProcessManager\Process\ProcProcess;

class Manager
{
    protected string $status = 'ready';

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

    /**
     * psr-3 logger
     */
    protected LoggerInterface $logger;

    /**
     * @var array{
     *     name: string,
     *     logger: LoggerInterface | array{
     *         level: LogLevel
     *     },
     *     runtime: string
     * } $config
     */
    protected array $config;

    /**
     * @param array{
     *     name?: string,
     *     logger?: LoggerInterface | array{
     *         level: LogLevel
     *     },
     *     runtime?: string
     * }|null $config
     */
    public function __construct(?array $config = null)
    {
        $defaultConfig = $this->getDefaultConfig();
        if ($config) {
            $defaultConfig = array_merge($defaultConfig, $config);
        }
        $this->config = $defaultConfig;
        $this->initLogger();
        cli_set_process_title($this->config['name']);
    }

    /**
     * default config
     *
     * @return array{
     *      name: string,
     *      logger: array{
     *          level: LogLevel
     *      },
     *      runtime: string
     *  }
     */
    protected function getDefaultConfig(): array
    {
        return [
            'name' => 'PHP Process Manager',
            'logger' => [
                'level' => LogLevel::INFO,
            ],
            'runtime' => __DIR__.'/runtime',
        ];
    }

    /**
     * Init default logger
     *
     * @return $this
     */
    protected function initLogger(): static
    {
        $config = $this->config;
        $loggerConfig = $config['logger'];
        if ($loggerConfig instanceof LoggerInterface) {
            $this->logger = $loggerConfig;
        } elseif (is_array($loggerConfig)) {
            $runtime = $config['runtime'];
            $logFile = $runtime.'/process.log';
            $logger = new Logger($this->config['name']);
            $level = $loggerConfig['level'];
            $logger->pushHandler(new StreamHandler($logFile, $level));
            $this->setLogger($logger);
        }

        return $this;
    }

    /**
     * Set psr-3 logger
     */
    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
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
        if ($process->needRestart() && $this->status === 'running') {
            $this->restartProcesses[] = $process;
        }

        return $this;
    }

    /**
     *  Process success callback
     */
    protected function handleProcessSuccess(ProcessInterface $process): void
    {
        $this->logger->info($this->getProcessTag($process, 'Down'), $process->getInfo(true));
        $process->triggerSuccessEvent();
        $this->addRestartProcess($process);
    }

    /**
     * Process timeout callback
     */
    protected function handleProcessTimeout(ProcessInterface $process, ProcessTimedOutException $exception): void
    {
        $this->logger->info($this->getProcessTag($process, 'Timeout'), $process->getInfo(true));
        $process->triggerTimeoutEvent();
        $this->addRestartProcess($process);
    }

    /**
     * Handle error process
     */
    protected function handleProcessError(ProcessInterface $process): void
    {
        $this->logger->error(
            $this->getProcessTag($process, 'Error'),
            array_merge(
                $process->getInfo(true),
                [
                    'error' => $process->getErrorOutput(),
                ]
            )
        );
        $process->triggerErrorEvent();
        $this->addRestartProcess($process);
    }

    /**
     * Manager end
     */
    protected function end(): void
    {
        $this->logger->info('End Manager');
    }

    public function exit(int $signal = 0): void
    {
        $this->status = 'exiting';
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
                $this->logger->info($this->getProcessTag($process, 'start'), $process->getInfo());
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
            $this->logger->info($this->getProcessTag($process, 'restart'), $process->getInfo());
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
        $this->status = 'running';
        $this->logger->info('Start Manager');
        $this->registerSignalHandler();
        $this->startProcesses();
        $sleepTime = 100000;
        while (true) {
            $this->runningProcesses = array_filter($this->runningProcesses, function (ProcessInterface $process) {
                if (! $process->isRunning()) {
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
                        $this->handleProcessTimeout($process, $exception);

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
            usleep($sleepTime);
        }
        $this->end();
    }
}
