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
    /**
     * manager identified name
     * @var string|null
     */
    protected string|null $name = null;

    /**
     * processes which is running
     * @var array<ProcessInterface>
     */
    protected array $runningProcesses = [];

    /**
     * processes which is running
     * @var array<ProcessInterface>
     */
    protected array $restartProcesses = [];

    /**
     * processes waiting to start
     * @var array<Job>
     */
    protected array $jobs = [];

    /**
     * psr-3 logger
     * @var LoggerInterface
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
            "name" => "PHP Process Manager",
            "logger" => [
                "level" => LogLevel::INFO
            ],
            "runtime" => __DIR__ . "/runtime"
        ];
    }

    /**
     * Init default logger
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
            $logFile = $runtime . "/process.log";
            $logger = new Logger("process-manager");
            $level = $loggerConfig['level'];
            $logger->pushHandler(new StreamHandler($logFile, $level));
            $this->setLogger($logger);
        }
        return $this;
    }

    /**
     * Set psr-3 logger
     * @param LoggerInterface $logger
     * @return static
     */
    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Add CMD Job
     * @param array<string> $cmd
     * @return ProcJob
     */
    public function spawnCmd(array $cmd): ProcJob
    {
        $job = new ProcJob(new ProcProcess($cmd));
        $this->jobs[] = $job;
        return $job;
    }

    /**
     * Add PHP Process
     * @param callable $callback
     * @return PcntlJob
     */
    public function spawnPhp(callable $callback): PcntlJob
    {
        if (!is_callable($callback)) {
            throw new LogicException('Params callback must can be called as a function');
        }
        $job = new PcntlJob(new PcntlProcess($callback));
        $this->jobs[] = $job;
        return $job;
    }

    /**
     * @param ProcessInterface $process
     * @return $this
     */
    protected function addRestartProcess(ProcessInterface $process): static
    {
        if ($process->needRestart()) {
            $this->restartProcesses[] = $process;
        }
        return $this;
    }


    /**
     *  Process success callback
     * @param ProcessInterface $process
     * @return void
     */
    protected function handleProcessSuccess(ProcessInterface $process): void
    {
        $this->logger->info($this->getProcessTag($process, "Down"), $process->getInfo(true));
        $process->triggerSuccessEvent();
        $this->addRestartProcess($process);
    }

    /**
     * Process timeout callback
     * @param ProcessInterface $process
     * @param ProcessTimedOutException $exception
     * @return void
     */
    protected function handleProcessTimeout(ProcessInterface $process, ProcessTimedOutException $exception): void
    {
        $this->logger->info($this->getProcessTag($process, "Timeout"), $process->getInfo(true));
        $process->triggerTimeoutEvent();
        $this->addRestartProcess($process);
    }


    /**
     * Handle error process
     * @param ProcessInterface $process
     * @return void
     */
    protected function handleProcessError(ProcessInterface $process): void
    {
        $this->logger->error(
            $this->getProcessTag($process, "Error"),
            array_merge(
                $process->getInfo(true),
                [
                    "error" => $process->getErrorOutput()
                ]
            )
        );
        $process->triggerErrorEvent();
        $this->addRestartProcess($process);
    }

    /**
     * Manager end
     * @return void
     */
    protected function end(): void
    {
        $this->logger->info("End Manager");
    }

    public function exit(): void
    {
        $this->stopProcesses();
        exit;
    }

    /**
     * Start processes
     * @return $this
     */
    protected function startProcesses(): static
    {
        foreach ($this->jobs as $job) {
            foreach ($job as $process) {
                $process->start();
                $this->runningProcesses[] = $process;
                $this->logger->info($this->getProcessTag($process, "start"), $process->getInfo());
            }
        }
        return $this;
    }

    /**
     * Restart processes
     * @return $this
     */
    protected function restartProcesses(): static
    {
        foreach ($this->restartProcesses as $process) {
            $process = $process->restart();
            $pid = $process->getPid();
            $this->runningProcesses[$pid] = $process;
            $this->logger->info($this->getProcessTag($process, "restart"), $process->getInfo());
        }
        $this->restartProcesses = [];
        return $this;
    }

    /**
     * Stop all processes
     * @return void
     */
    public function stopProcesses(): void
    {
        foreach ($this->runningProcesses as $process) {
            $process->stop();
        }
    }

    /**
     * Register Signal Handler
     * @return void
     */
    protected function registerSignalHandler(): void
    {
        pcntl_signal(SIGTERM, [$this, 'exit']);
        pcntl_signal(SIGINT, [$this, 'exit']);
        pcntl_signal(SIGTSTP, [$this, 'exit']);
    }


    /**
     * @param ProcessInterface $process
     * @param string $action
     * @return string
     */
    protected function getProcessTag(ProcessInterface $process, string $action): string
    {
        return sprintf("Process[%s][%d] %s", $process->getUid(), $process->getCurrentRunTimes(), $action);
    }

    /**
     * Start Manager
     * @return void
     */
    public function start(): void
    {
        $this->logger->info("Start Manager");
        $this->registerSignalHandler();
        $this->startProcesses();
        $sleepTime = 100000;
        while (true) {
            $this->runningProcesses = array_filter($this->runningProcesses, function (ProcessInterface $process) {
                if (!$process->isRunning()) {
                    echo "haha";
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
            if (sizeof($this->restartProcesses) > 0) {
                $this->restartProcesses();
            }
            if (sizeof($this->runningProcesses) === 0) {
                break;
            }
            pcntl_signal_dispatch();
            usleep($sleepTime);
        }
        $this->end();
    }
}
