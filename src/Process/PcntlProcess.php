<?php

declare(strict_types=1);

namespace Zjwshisb\ProcessManager\Process;

use BadMethodCallException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
use Zjwshisb\ProcessManager\Process\Traits\Event;
use Zjwshisb\ProcessManager\Process\Traits\HasUid;
use Zjwshisb\ProcessManager\Process\Traits\Repeatable;
use Zjwshisb\ProcessManager\Process\Traits\WithEndTime;

class PcntlProcess implements ProcessInterface
{
    use Event;
    use HasUid;
    use Repeatable;
    use WithEndTime;

    /**
     * @var array{
     *     signaled?: bool,
     *     exitcode?: int|null,
     *     termsig?: int|null,
     *     pid?: int|null,
     *     running?: bool
     * }
     */
    private array $processInformation = [];

    private ?float $starttime = null;

    private ?float $timeout = null;

    private ?int $exitcode = null;

    private string $status = Process::STATUS_READY;

    /**
     * @var array{
     *     signaled?: bool,
     *     exitcode?:int,
     *     termsig?:int,
     * }
     */
    private array $fallbackStatus = [];

    private ?int $latestSignal = null;

    /** @var resource|null */
    private $socket;

    private ?string $output = null;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * @param  callable  $callback
     *                              $callback on can return string
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function setTimeout(float $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function start(): void
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (! $sockets) {
            throw new RuntimeException('Failed to create socket pair.');
        }
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new RuntimeException('pcntl_fork() failed');
        }
        // child process
        if ($pid === 0) {
            $this->run($sockets);
        } else {
            // parent process
            $this->addRunTimes();
            fclose($sockets[1]);
            $this->socket = $sockets[0];
            $this->processInformation['pid'] = $pid;
            $this->starttime = microtime(true);
            $this->status = Process::STATUS_STARTED;
            $this->updateStatus();
        }
    }

    /**
     * @param  array<resource>  $sockets
     */
    protected function run(array $sockets): void
    {
        cli_set_process_title('php pcntl process worker');
        fclose($sockets[0]);
        // reset random seeder
        mt_srand(posix_getpid());
        $exitCode = 0;
        try {
            $result = call_user_func($this->callback, $this);
        } catch (Throwable $throwable) {
            $result = $throwable->getMessage();
            $exitCode = 2;
        }
        if ($result) {
            fwrite($sockets[1], serialize($result));
        }
        fclose($sockets[1]);
        exit($exitCode);
    }

    protected function updateStatus(): void
    {
        if ($this->status !== Process::STATUS_STARTED) {
            return;
        }
        if (empty($this->fallbackStatus) || empty($this->fallbackStatus['signaled'])) {
            $pid = $this->getPid();
            if (! $pid) {
                throw new RuntimeException('Failed to get process pid.');
            }
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result == $this->getPid()) {
                if (pcntl_wifexited($status)) {
                    $exitcode = pcntl_wexitstatus($status);
                    if ($exitcode !== false) {
                        $this->exitcode = $exitcode;
                    }
                }
                $this->processInformation['exitcode'] = $this->exitcode;
                $this->processInformation['signaled'] = false;
                $this->status = Process::STATUS_TERMINATED;
                $this->processInformation['running'] = false;
                $this->close();
            } else {
                $this->processInformation['running'] = true;
            }
        }
    }

    public function isRunning(): bool
    {
        if ($this->status !== Process::STATUS_STARTED) {
            return false;
        }
        $this->updateStatus();

        return $this->processInformation['running'] ?? false;
    }

    public function getPid(): ?int
    {
        return $this->processInformation['pid'] ?? null;
    }

    public function getInfo(bool $withExit = false): array
    {
        $info = [
            'type' => 'pcntl',
            'pid' => $this->getPid(),
        ];
        if ($withExit) {
            $info['exit code'] = $this->getExitCode();
            $info['exit text'] = $this->getExitCodeText();
        }

        return $info;
    }

    public function stop(float $timeout = 0, ?int $signal = SIGTERM): ?int
    {
        if ($this->isRunning()) {
            $this->doSignal($signal, false);
        }

        return $this->close();
    }

    protected function close(): ?int
    {
        if ($this->fallbackStatus && ! empty($this->fallbackStatus['signaled'])) {
            $this->processInformation = $this->fallbackStatus + $this->processInformation;
            $this->processInformation['running'] = false;
        }
        $this->exitcode = $this->processInformation['exitcode'] ?? -1;
        $this->status = Process::STATUS_TERMINATED;
        $this->updateEndTime();
        if ($this->exitcode === -1) {
            if (! empty($this->processInformation['signaled'])
                && isset($this->processInformation['termsig'])
                && $this->processInformation['termsig'] > 0) {
                $this->exitcode = 128 + $this->processInformation['termsig'];
            }
        }
        if ($this->socket) {
            $output = fgets($this->socket);
            if ($output !== false) {
                $this->output = $output;
            } else {
                $this->output = '';
            }
            fclose($this->socket);
            $this->socket = null;
        }

        return $this->exitcode;
    }

    public function doSignal(?int $signal = null, bool $throwException = true): bool
    {
        if (! $signal) {
            $signal = SIGTERM;
        }
        if (null === $pid = $this->getPid()) {
            if ($throwException) {
                throw new LogicException('Cannot send signal on a non running process.');
            }

            return false;
        }
        posix_kill($pid, $signal);
        $this->latestSignal = $signal;
        $this->fallbackStatus['signaled'] = true;
        $this->fallbackStatus['exitcode'] = -1;
        $this->fallbackStatus['termsig'] = $this->latestSignal;

        return true;
    }

    public function getOutput(): mixed
    {
        if ($this->isSuccessful()) {
            if ($this->output) {
                return unserialize($this->output);
            }
        }

        return '';
    }

    public function getErrorOutput(): string
    {
        if (! $this->isSuccessful()) {
            if ($this->output) {
                return unserialize($this->output);
            }

            return '';
        }

        return '';
    }

    public function isSuccessful(): bool
    {
        return $this->getExitCode() == 0;
    }

    public function restart(): static
    {
        if ($this->isRunning()) {
            throw new RuntimeException('Process is already running.');
        }
        $process = clone $this;
        $process->start();

        return $process;
    }

    private function resetProcessData(): void
    {
        $this->starttime = null;
        $this->endTime = null;
        $this->exitcode = null;
        $this->processInformation = [];
        $this->latestSignal = null;
        $this->fallbackStatus = [];
        $this->socket = null;
        $this->output = null;
        $this->status = Process::STATUS_READY;
    }

    public function checkTimeout(): void
    {
        if ($this->status !== Process::STATUS_STARTED) {
            return;
        }
        if ($this->timeout !== null && $this->timeout < microtime(true) - $this->starttime) {
            $this->stop();
            throw new ProcessTimedOutException($this, SymfonyProcessTimedOutException::TYPE_GENERAL);
        }
    }

    public function getExitCode(): ?int
    {
        $this->updateStatus();

        return $this->exitcode;
    }

    public function getExitCodeText(): ?string
    {
        if (null === $exitcode = $this->getExitCode()) {
            return null;
        }

        return Process::$exitCodes[$exitcode] ?? 'Unknown error';
    }

    public function getIdleTimeout(): ?float
    {
        return null;
    }

    public function getTimeout(): ?float
    {
        return $this->timeout;
    }

    public function getCommandLine(): ?string
    {
        return null;
    }

    public function isStarted(): bool
    {
        return $this->status != Process::STATUS_READY;
    }

    public function isTerminated(): bool
    {
        $this->updateStatus();

        return $this->status == Process::STATUS_TERMINATED;
    }

    public function __clone()
    {
        $this->resetProcessData();
    }

    public function __sleep(): array
    {
        throw new BadMethodCallException('Cannot serialize '.__CLASS__);
    }

    public function __wakeup()
    {
        throw new BadMethodCallException('Cannot unserialize '.__CLASS__);
    }

    public function getStartTime(): float
    {
        if ($this->isStarted() || is_null($this->starttime)) {
            throw new LogicException('Start time is only available after process start.');
        }

        return $this->starttime;
    }
}
