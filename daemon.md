## Daemon Processes

Traditionally daemon processes are created whereby a parent issues a `fork` system call which results in the process being split into two separate processes. All the resources are copied to a new address space for the new process so there is nothing shared. The result of `fork`ing returns a process id for the parent and nothing for the child so that the parent process exits, leaving the child running in the background.

This is the manner in which most processing in Linux systems are daemonized and run via `/etc/init.d`. Additionally, in order to monitor the process, the startup script will usually issue a file that contains the process id of the running daemon. This is used to determine which process id needs to be interrupted if the daemon needs to be stopped or restarted.

In Bash and other shells, there are other ways of bringing processes into the background, such as `Ctrl+Z` or add `&` to the end of the command before running. However, these methods are not useful for creating daemons. They are useful for push a task to the background but not appropriate for long-term daemon solutions.

## A Simple PHP Daemon

A very simple example of a daemon in PHP is to use `pcntl` functions. You can check (from shell) if you have it installed by running this command: `php -m | grep pcntl > /dev/null 2>&1 && echo "Installed" || echo "Not installed"`. If you don't have this module, read more on [installation](http://php.net/pcntl).

We will use the `pcntl_fork` method to create a daemon.

```php
if (!function_exists('pcntl_fork')) {
    die('You need pcntl for ths to work.');
}

//fork
$pid = pcntl_fork();

//check whether we are parent or child
if ($pid == -1) {
    die('Could not fork.');
}

if ($pid) {
    echo "Creating child process $pid to run as daemon.";
    exit(0);
}

//child process continues here...
doSomething();
```

There are a few important consideration for daemon processes.

 1. Input and output. You should never write to stdout or read from stdin while in the background. If you write to stdout (i.e. echo or print) you will be writing this data in an unpredictable way on the terminal that originally created this process. You should only write to a file or some other non-stdout mechanism. A good example would be to create a file in /var/log directory as a means for logging.
 2. As a daemon process, usually it runs until told to stop. This means that somewhere you have a `while(true)` that checks for various conditions, does something, possibly sleeps if there's nothing to do, and repeats.
 3. Honor signals by creating your own signal handlers. Assume the worst case. Assume that you will may be interrupted at a point in time that would make exiting bad for your application. You can handle interrupts by introducing your own signal handlers with `pcntl_signal` function. This would allow you to do some sort of "cleanup" if deemed necessary by your process prior to exiting.
 4. Always assume something else may be trying to access your resources simultaneously. This forces you to design your application in such a way that you are trying to avoid race conditions or deadlocks. Example: you want to write data to a file. Did you try to acquire a writing lock with `flock`? If not, you should be. Another example: you need to bind to a particular port to listen for connections? Assume that someone may already be using that port.

