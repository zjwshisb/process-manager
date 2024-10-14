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

    public function __construct(?array $config = null)
    {
        if (!empty($config['name'])) {
            $this->name = $config['name'];
        } else {
            $this->name = "PHP Process Manager";
        }
        $this->initLogger($config);
        cli_set_process_title($this->name);
    }

    /**
     * Init default logger
     * @param array|null $config
     * @return $this
     */
    protected function initLogger(?array $config = null): static
    {
        $loggerConfig = $config['logger'] ?? [
            "level" => LogLevel::INFO,
        ];
        $runtime = $config['runtime'] ?? __DIR__ . "/runtime";
        $logFile = $runtime . "/" . $this->name . ".log";
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
     * Add Process
     *
     * @param array $cmd
     *
     * @param array{
     *     timeout?: int,
     *     cwd?: string,
     *     env?: array,
     *     input?: mixed
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
    public function spawn(array|\Closure $cmd, array $options = [], int $processCount = 1, bool|int $repetitive = false): static
    {
        $timeout = $options['timeout'] ?? 60;
        while ($processCount > 0) {
            if (is_callable($cmd)) {
                $process = new PcntlProcess($cmd, $timeout);
            } else {
                $process = new ProcProcess($cmd,
                    $options['cwd'] ?? null,
                        $options['env'] ?? null,
                    $options['input'] ?? null,
                    $timeout
                );
            }
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
    public function startProcesses(bool $isRestart = false): static
    {
        $name = $isRestart ? "restart" : "start";
        foreach ($this->waitingProcesses as $process) {
            if ($isRestart) {
                $process= $process->restart();
            } else {
                $process->start();
            }
            $pid = $process->getPid();
            $this->runningProcesses[$pid] = $process;
            $this->logger->info("$name Process", $process->getInfo());
        }
        $this->waitingProcesses = [];
        return $this;
    }

    /**
     * Handle success process
     * @param ProcessInterface $process
     * @return void
     */
    public function handleProcessSuccess(ProcessInterface $process): void
    {
        $this->logger->info("Process End", $process->getInfo(true));
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
    public function handleProcessTimeout(ProcessInterface $process, ProcessTimedOutException $exception): void
    {
    }

    /**
     * Handle error process
     * @param ProcessInterface $process
     * @return void
     */
    public function handleProcessError(ProcessInterface $process): void
    {
        $this->logger->error("Process Error", array_merge(
            $process->getInfo(true),
            [
                "error" => $process->getErrorOutput()
            ])
        );
    }

    /**
     * Manager end
     * @return void
     */
    public function end(): void
    {
        $this->logger->info("Auto End Process Manager");
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

    public function registerSignalHandler(): void
    {
        pcntl_signal(SIGTERM, [$this, 'exit']);
        pcntl_signal(SIGINT,[$this, 'exit']);
        pcntl_signal(SIGTSTP, [$this, 'exit']);
    }

    /**
     * Start Manager
     * @return void
     */
    public function start(): void
    {
        $this->logger->info("Start Process Manager");
        $this->registerSignalHandler();
        $this->startProcesses();
        while (true) {
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
                        $this->handleProcessTimeout($exception->getProcess(), $exception);
                        return true;
                    }
                }
                return true;
            });
            if (sizeof($this->waitingProcesses)) {
                $this->startProcesses(true);
            }
            if (sizeof($this->runningProcesses) === 0) {
                break;
            }
            pcntl_signal_dispatch();
            sleep(1);
        }
        $this->end();
        echo "end" . PHP_EOL;
    }
}