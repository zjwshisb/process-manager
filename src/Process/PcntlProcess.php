<?php
namespace Zjwshisb\ProcessManager\Process;


use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Zjwshisb\ProcessManager\Exception\ProcessTimedOutException;
use Zjwshisb\ProcessManager\Traits\HasUuid;
use Zjwshisb\ProcessManager\Traits\Repeatable;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;


class PcntlProcess implements ProcessInterface {

    use HasUuid,Repeatable;

    private array $processInformation = [];

    private ?float $starttime = null;
    private ?float $timeout;

    private ?int $exitcode = null;

    private string $status = Process::STATUS_READY;

    private array $fallbackStatus = [];

    private ?int $latestSignal = null;

    public function __construct(public \Closure $closure, float $timeout = 60 )
    {
        $this->timeout = $timeout;
        $this->setUuid();
    }

    public function start(): void
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new LogicException("pcntl_fork() failed");
        }
        if ($pid === 0) {
            cli_set_process_title("php pcntl process work");
            call_user_func($this->closure);
            exit;
        } else {
            $this->processInformation["pid"] = $pid;
            $this->starttime = microtime(true);
        }
        $this->status = Process::STATUS_STARTED;
        $this->updateStatus();
    }

    protected function updateStatus(): void
    {
        if ($this->status !== Process::STATUS_STARTED) {
            return;
        }
        if (empty($this->fallbackStatus) || !empty($this->fallbackStatus['signaled'])) {
            $result = pcntl_waitpid($this->getPid(), $status, WNOHANG );
            if( $result == $this->getPid()){
                if (pcntl_wifexited($status)) {
                    $this->exitcode = pcntl_wexitstatus($status);
                }
                var_dump($this->exitcode);
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
        if( $this->status !== Process::STATUS_STARTED) {
            return false;
        }
        $this->updateStatus();
        return $this->processInformation['running'] ?? false;
    }

    public function getPid() :?int
    {
        return $this->processInformation["pid"] ?? null;
    }

    public function getInfo(bool $withExit = false): array
    {
       $info = [
            "type" => "pcntl",
           "pid" => $this->getPid(),
       ];
        if ($withExit) {
            $info["exit code"] = $this->getExitCode();
            $info['exit text'] = $this->getExitCodeText();
        }
        return $info;
    }

    public function stop($signal= SIGTERM): void
    {
        if ($this->isRunning()) {
            $this->doSignal($signal, false);
        }
        $this->close();
    }


    public function signal(int $signal): static
    {
        $this->doSignal($signal, false);
        return $this;
    }

    public function close()
    {
        if ($this->fallbackStatus && !empty($this->fallbackStatus['signaled'])) {
            $this->processInformation = $this->fallbackStatus + $this->processInformation;
            $this->processInformation['running'] = false;
        }
        $this->exitcode = $this->processInformation['exitcode'] ?? -1;
        $this->status = Process::STATUS_TERMINATED;
        if (-1 === $this->exitcode) {
            if (!empty($this->processInformation['signaled'])
                && isset($this->processInformation['termsig'])
                && 0 < $this->processInformation['termsig']) {
                $this->exitcode = 128 + $this->processInformation['termsig'];
            }
        }
        return $this->exitcode;
    }

    public function doSignal(int $signal, $throwException = true): bool
    {
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

    public function getErrorOutput(): string
    {
       return "no";
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

    public function __clone()
    {
        $this->resetProcessData();
    }

    private function resetProcessData(): void
    {
        $this->starttime = null;
        $this->exitcode = null;
        $this->processInformation = [];
        $this->status = Process::STATUS_READY;
        $this->latestSignal = null;
        $this->fallbackStatus = [];
    }

    public function checkTimeout(): void
    {
        if (Process::STATUS_STARTED !== $this->status) {
            return;
        }
        if (null !== $this->timeout && $this->timeout < microtime(true) - $this->starttime) {
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
}