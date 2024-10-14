<?php

namespace Zjwshisb\ProcessManager;


use JetBrains\PhpStorm\NoReturn;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Zjwshisb\ProcessManager\Process\PcntlProcess;
use Zjwshisb\ProcessManager\Process\ProcessInterface;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
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
     * processes waiting to start
     * @var array<ProcessInterface>
     */
    protected array $waitingProcesses = [];

    /**
     * psr-3 logger
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    protected array $config;

    public function __construct(?array $config = null)
    {
        $config = array_merge($this->getDefaultConfig(), $config ?? [] );
        $this->config = $config;;
        $this->initLogger($config);
        cli_set_process_title($config['name']);
    }

    public function getDefaultConfig() : array
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
     * @param array $config
     * @return $this
     */
    protected function initLogger(array $config): static
    {
        $loggerConfig = $config['logger'];
        $runtime = $config['runtime'];
        $logFile = $runtime . "/process.log";
        if ($loggerConfig instanceof LoggerInterface) {
            $this->logger = $loggerConfig;
        } elseif (is_array($loggerConfig)) {
            $logger = new Logger("process-manager");
            $level = $loggerConfig['level'] ?? LogLevel::INFO;
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
     * Add waiting process
     * @param ProcessInterface $process
     * @return $this
     */
    protected function addProcess(ProcessInterface $process): static
    {
        $this->waitingProcesses[] = $process;
        return $this;
    }

    /**
     * Add CMD Process
     *
     * @param array $cmd
     * @param array{
     *     timeout?: int,
     *     cwd?: string,
     *     env?: array,
     *     input?: mixed,
     * } $options
     *
     * @param int $processCount
     * how many process to start
     *
     * @param bool|int $repetitive
     * define process run times.
     * false mean run once each process.
     * ture mean run repetitive each process.
     * integer mean run {integer} times each process.
     *
     * @return static
     */
    public function spawnCMD(array $cmd, array $options = [], int $processCount = 1, bool|int $repetitive = false): static
    {
        $timeout = $options['timeout'] ?? 60;
        while ($processCount > 0) {
            $process = new ProcProcess($cmd,
                $options['cwd'] ?? null,
                $options['env'] ?? null,
                $options['input'] ?? null,
                $timeout
            );
            $process->setRepeatable($repetitive);
            $this->addProcess($process);
            $processCount--;
        }
        return $this;
    }

    /**
     * Add PHP Process
     *
     * @param array|callable|string $cmd
     * @param int $timeout
     * @param int $processCount
     * how many process to start
     *
     * @param bool|int $repetitive
     * define process run times.
     * false mean run once each process.
     * ture mean run repetitive each process.
     * integer mean run {integer} times each process.
     *
     * @return static
     */
    public function spawnPHP(array|callable|string $cmd, int $timeout = 60 , int $processCount = 1, bool|int $repetitive = false): static
    {
        while ($processCount > 0) {
            $process = new PcntlProcess($cmd, $timeout);
            $process->setRepeatable($repetitive);
            $this->addProcess($process);
            $processCount--;
        }
        return $this;
    }

    /**
     * Start waiting processes
     * @param bool $isRestart
     * @return $this
     */
    protected function startProcesses(bool $isRestart = false): static
    {
        $action = $isRestart ? "Restart" : "Start";
        foreach ($this->waitingProcesses as $process) {
            if ($isRestart) {
                $process = $process->restart();
            } else {
                $process->start();
            }
            $pid = $process->getPid();
            $this->runningProcesses[$pid] = $process;
            $this->logger->info($this->getProcessTag($process, $action), $process->getInfo());
        }
        $this->waitingProcesses = [];
        return $this;
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
            $this->addProcess($process);
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
            $this->addProcess($process);
        }
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
            $this->addProcess($process);
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
        $this->stopProcess();
        exit;
    }

    public function stopProcess(): void
    {
        foreach ($this->runningProcesses as $process) {
            $process->stop();
        }
    }

    protected function registerSignalHandler(): void
    {
        pcntl_signal(SIGTERM, [$this, 'exit']);
        pcntl_signal(SIGINT,[$this, 'exit']);
        pcntl_signal(SIGTSTP, [$this, 'exit']);
    }


    protected function getProcessTag(ProcessInterface $process , string $action): string
    {
        return sprintf("Process[%s][%d] %s",  $process->getUuid(), $process->getRunCount(), $action);
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
                if (sizeof($this->waitingProcesses) > 0) {
                    $this->startProcesses(true);
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