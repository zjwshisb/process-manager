# A Simple Php Process Manager
This package provider an easy way to executes php callable in sub-processes with pcntl extension and executes commands in sub-processes
with [symfony/process](https://github.com/symfony/process) package.

<a href="https://github.com/zjwshisb/process-manager/actions">
<img alt="style" src="https://img.shields.io/github/actions/workflow/status/zjwshisb/process-manager/style.yml?logo=%3D&label=style" />
</a>
<a href="https://github.com/zjwshisb/process-manager/actions">
<img alt="test" src="https://img.shields.io/github/actions/workflow/status/zjwshisb/process-manager/tester.yml?logo=%3D&label=test" />
</a>
<a href="https://github.com/zjwshisb/process-manager/actions">
<img alt="phpstan" src="https://img.shields.io/github/actions/workflow/status/zjwshisb/process-manager/phpstan.yml??logo=%3D&label=phpstan" />
</a>
<a href="https://github.com/zjwshisb/process-manager/actions">
<img alt="coverage" src="https://img.shields.io/codecov/c/github/zjwshisb/process-manager" />
</a>


## Example
Setting multiple process to consume job or run shell commands.  

First, white a script like below.
```php
// script.php
$manager = new \Zjwshisb\ProcessManager\Manager();
$manager->setLogger();
// setting php callback 
$manager->spawnPhp(function () {
  $count = 1;
  // pseudocode
  // get job to do 
  // for Prevent memory leakage, after reach 100 times, exit
  while ($count <= 100) {
    if ($job = Queue::getJob()) {
        $job->handle();
        $count++;
    } else {
      sleep(1);
    }       
  }
})
// 10 processes to run
->setProcessCount(10);
// processes will always restart after exit.
->setRuntime(0);
// or execute shell commands,
$manager->spawnCmd(["shell command"])
->setProcessCount(10);
->setRuntime(0);
// can call spawnPhp/spawnCmd multiple times to add different jobs. 
$manager->start();

```
Then, Run the script.php.
```shell
/path/to/php scipt.php
```
It is better to use supervisor to keep the script alive.  

Supervisor config file like below.
```
[program:process-manager]
command=/path/to/php /path/to/script.php
autostart=true
autorestart=true
stderr_logfile=/path/to/stderr.log
stdout_logfile=/path/to/stdout.log
numprocs=1
```

## Installation

### Requirements
* php >= 8.1
* ext-pcntl
* ext-posix
* Linux/Macos

### Composer
```shell
composer require zjwshisb/process-manager:1.0
```

## Usage

Basic usage:

```php
$manager = new \Zjwshisb\ProcessManager\Manager();
// or
$manager = new \Zjwshisb\ProcessManager\Manager("PHP Process Manager", "/var/runtime/", 100 * 1000);
// custom logger setting, any instance implement Psr\Log\LoggerInterface support
// if null mean set to Monolog\Logger
$manager->setLogger()
// executes the php callback.
$manager->spawnPhp(function () {return "hello world"})
// executes commands.
$manager->spawnCmd(["echo", "hello world"])
        // below methods only support in spawnCmd
        // ->setEnv()
        // ->setInput()
        // ->setWorkingDirectory()
// start all process
$manager->start();
```
Get success output:

```php
// spawnCmd is the same usage.
$manager = new \Zjwshisb\ProcessManager\Manager();
$manager->spawnPhp(function () {return "hello world"})
        ->onSuccess(function (\Zjwshisb\ProcessManager\Process\PcntlProcess $process) {
           // this will be "hello world"
           $output = $process->getOutput();
        })
$manager->start();
```
Get error output:

```php
// spawnCmd is the same usage.
$manager = new \Zjwshisb\ProcessManager\Manager();
$manager->spawnPhp(function () {
            throw new RuntimeException("hello world")
        })
        ->onError(function (\Zjwshisb\ProcessManager\Process\PcntlProcess $process) {
           // this will be "hello world"
           $output = $process->getErrorOutput();
        })
$manager->start();
```

Set timeout:

```php
$manager = new \Zjwshisb\ProcessManager\Manager();

// set timeout, default 60 seconds.
// set to 0 mean no timeout.
// spawnCmd is the same usage.
$manager->spawnPhp(function () {sleep(10);})
        ->setTimeout(5)
        ->onTimeout(function () {
            //any things to do after timeout
        })
$manager->start();
```


Run multiple times: 

```php
$manager = new \Zjwshisb\ProcessManager\Manager();
// this will echo 1 10 times totally.
// spawnCmd is the same usage.
$manager->spawnPhp(function () {echo  1 . PHP_EOL;})
        // set run times, default to 1
        ->setRunTimes(10)
$manager->start();
```

Run Always:

```php
$manager = new \Zjwshisb\ProcessManager\Manager();
// this will echo 1 always util being stop by other signal.
// spawnCmd is the same usage. 
$manager->spawnPhp(function () {echo  1 . PHP_EOL;})
// if value set to zero or negative, the callback will run infinitely.
        ->setRunTimes(0)
$manager->start();
```

Set Multiple process:

```php
$manager = new \Zjwshisb\ProcessManager\Manager();
// this will "echo 1" 10 times totally.
// spawnCmd is the same usage.
$manager->spawnPhp(function () {echo  1 . PHP_EOL;})
        // set process count, default to 1.
        ->setProcessCount(10)
$manager->start();
```

Multiple process run multiple times:

```php
$manager = new \Zjwshisb\ProcessManager\Manager();
// this will "echo 1" 10*10 times totally.
// spawnCmd is the same usage.
$manager->spawnPhp(function () {echo  1 . PHP_EOL;})
        // set process count, default to 1.
        ->setProcessCount(10)
        ->setRunTimes(10)
$manager->start();
```

## License

MIT