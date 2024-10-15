<?php

namespace Zjwshisb\ProcessManager;

use JetBrains\PhpStorm\NoReturn;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Exception\LogicException;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
use Zjwshisb\ProcessManager\Group\ProcessGroup;
use Zjwshisb\ProcessManager\Group\ProcessGroupInterface;
use Zjwshisb\ProcessManager\Group\ProcProcessGroup;
use Zjwshisb\ProcessManager\Process\PcntlProcess;
use Zjwshisb\ProcessManager\Process\ProcessInterface;
use Zjwshisb\ProcessManager\Process\ProcProcess;

class Manager
{
    /**
     * manager identified name
     * @var string|mixed
     */
    protected string $name;

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
     * @var array<ProcessGroupInterface>
     */
    protected array $processGroup = [];

    /**
     * psr-3 logger
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var array $config
     */
    protected array $config;

    public function __construct(?array $config = null)
    {
        $this->config = array_merge($this->getDefaultConfig(), $config ?? []);
        $this->initLogger();
        cli_set_process_title($config['name']);
    }

    /**
     * default config
     * @return array
     */
    public function getDefaultConfig(): array
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
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Add CMD Process
     * @param array $cmd
     * @param int $processCount
     * how many process to start
     * @return ProcessGroup
     */
    public function spawnCmd(array $cmd, int $processCount = 1): ProcessGroup
    {
        $group = new ProcProcessGroup();
        while ($processCount > 0) {
            $group->add(new ProcProcess($cmd));
            $processCount--;
        }
        $this->addProcessGroup($group);
        return $group;
    }

    /**
     * Add PHP Process
     *
     * @param array|callable|string $callback
     * @param int $processCount
     * how many process to start
     *
     * @return ProcessGroup
     */
    public function spawnPhp(array|callable|string $callback, int $processCount = 1): ProcessGroup
    {
        if (!is_callable($callback)) {
            throw new LogicException('Params callback must can be called as a function');
        }
        $group = new ProcessGroup();
        while ($processCount > 0) {
            $group->add(new PcntlProcess($callback));
            $processCount--;
        }
        $this->addProcessGroup($group);
        return $group;
    }


    /**
     * Handle success process
     * @param ProcessInterface $process
     * @return void
     */
    protected function handleProcessSuccess(ProcessInterface $process): void
    {
        $this->logger->info($this->getProcessTag($process, "Down"), $process->getInfo(true));
        if ($process->repeatable()) {
            $this->restartProcesses[] = $process;
        }
    }

    /**
     * Handle timeout process
     * @param ProcessInterface $process
     * @param ProcessTimedOutException $exception
     * @return void
     */
    protected function handleProcessTimeout(ProcessInterface $process, ProcessTimedOutException $exception): void
    {
        $this->logger->info($this->getProcessTag($process, "Timeout"), $process->getInfo(true));
        if ($process->repeatable()) {
            $this->restartProcesses[] = $process;
        }
    }

    protected function addProcessGroup(ProcessGroup $group): static
    {
        $this->processGroup[] = $group;
        return $this;
    }

    /**
     * Handle error process
     * @param ProcessInterface $process
     * @return void
     */
    protected function handleProcessError(ProcessInterface $process): void
    {
        $this->logger->error($this->getProcessTag($process, "Error"), array_merge(
                $process->getInfo(true),
                [
                    "error" => $process->getErrorOutput()
                ])
        );
        if ($process->repeatable()) {
            $this->restartProcesses[] = $process;
        }
    }

    /**
     * Manager end
     * @return void
     */
    protected function end(): void
    {
        $this->logger->info("End Manager");
    }

    #[NoReturn] public function exit(): void
    {
        $this->stopProcesses();
        exit;
    }

    /**
     * @return $this
     */
    protected function startProcesses(): static
    {
        foreach ($this->processGroup as $group) {
            foreach ($group as $process) {
                $process->start();
                $pid = $process->getPid();
                $this->runningProcesses[$pid] = $process;
                $this->logger->info($this->getProcessTag($process, "start"), $process->getInfo());
            }
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function restartProcesses(): static
    {
        foreach ($this->restartProcesses as $process) {
            $process->start();
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
        return sprintf("Process[%s][%d] %s", $process->getUid(), $process->getRunCount(), $action);
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
        $initSleepTime = 100000;
        $sleepTime = $initSleepTime;
        while (true) {
            $count = sizeof($this->runningProcesses);
            $this->runningProcesses = array_filter($this->runningProcesses, function (ProcessInterface $process) {
                if (!$process->isRunning()) {
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
            if ($count === sizeof($this->runningProcesses)) {
                $sleepTime = $sleepTime * 2;
            } else {
                $sleepTime = $initSleepTime;
                if (sizeof($this->restartProcesses) > 0) {
                    $this->restartProcesses();
                }
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