<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

namespace phlask;

/**
 * A runnable task definition allowing you to execute a task and monitor its
 * progress while it runs as well as be able to terminate or send a POSIX
 * signal to the process.
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */
class Task
{
    const STATUS_RUNNING                = 0;
    const STATUS_SIGNALED               = 1;
    const STATUS_STOPPED                = 2;
    const STATUS_COMPLETE               = 3;
    const STATUS_PENDING_TERMINATION    = 4;

    const SIG_HUP  = 1;
    const SIG_INT  = 2;
    const SIG_QUIT = 3;
    const SIG_ABRT = 6;
    const SIG_KILL = 9;
    const SIG_ALRM = 14;
    const SIG_TERM = 15;

    /**
     * Holds the timestamp of when this process started.
     *
     * @var int
     */
    protected $startTime;

    /**
     * The end time of this process.
     *
     * @var int
     */
    protected $endTime;

    /**
     * The task specification used to run this task.
     *
     * @var TaskSpecInterface
     */
    protected $taskSpec;

    /**
     * The pipes that were opened up by this task.
     * In this class, 0=>writable stdin, 1=>readable stdout, 2=>readable stderr
     *
     * @var array
     */
    protected $pipes = array();

    /**
     * The process resource produced by proc_open() method.
     *
     * @var resource
     */
    protected $process;

    /**
     * Process ID
     *
     * @var int
     */
    protected $pid;

    /**
     * Status of the process. Must be one of STATUS_* constants.
     *
     * @var int
     */
    protected $status;

    /**
     * The exit code if process is done.
     *
     * @var int
     */
    protected $exitCode;

    /**
     * The signal if signalled. One of static::SIG_* constants.
     *
     * @var int
     */
    protected $stopSignal;

    /**
     * The signal if terminated by signal. One of static::SIG_* constants.
     *
     * @var int
     */
    protected $termSignal;

    /**
     * Initializes this task with a particular spec.
     *
     * @param TaskSpecInterface $taskSpec The task specification.
     *
     * @return Task The task.
     */
    public static function factory(TaskSpecInterface $taskSpec)
    {
        return new self($taskSpec);
    }

    /**
     * Create a new task.
     *
     * @param TaskSpecInterface $taskSpec The task specification to use
     *                                    for this class.
     */
    public function __construct(TaskSpecInterface $taskSpec)
    {
        $this->taskSpec = $taskSpec;
    }

    /**
     * Release resources
     */
    public function __destruct()
    {
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
    }

    /**
     * Runs the task.
     *
     * @return Task The current task (for chaining).
     *
     * @throws Exception\ExecutionException
     */
    public function run()
    {
        //set up the task
        $cmd = $this->taskSpec->getCommand();
        $env = $this->taskSpec->getEnv();
        $cwd = $this->taskSpec->getCwd();

        //run it!
        //@todo set up an error handler to manage what might not work in proc_open to prevent errors
        $this->process = proc_open(
            $cmd,
            array(
                array('pipe', 'r'),//stdin wrt child
                array('pipe', 'w'),//stdout wrt child
                array('pipe', 'w'),//stderr wrt child
            ),
            $this->pipes,
            $cwd,
            $env
        );

        //do a check on the resource
        if (!is_resource($this->process)) {
            throw new Exception\ExecutionException(
                "Could not start process: '$cmd' in '$cwd'"
            );
        }

        //initialize some variables in this class
        $this->startTime = microtime(true);
        $this->status = static::STATUS_RUNNING;
        $this->statusCheck();

        return $this;
    }

    /**
     * Retrieves the status array.
     *
     * @return array The array spec defined in proc_get_status()
     */
    public function statusCheck()
    {
        $s = proc_get_status($this->process);

        //default status is that we're running
        if (!$this->status === null) {
            $this->status = static::STATUS_RUNNING;
        }

        //calculate runtime
        if ($this->status != static::STATUS_RUNNING) {
            $this->endTime = microtime(true);
        }

        //extract data from array
        $this->pid = isset($s['pid']) ? $s['pid'] : false;

        //extract status
        if (isset($s['running']) && $s['running'] === false) {
            if ($this->status != static::STATUS_PENDING_TERMINATION) {
                $this->status = static::STATUS_COMPLETE;
            }
            //Since multiple calls to this method will cause exit code to change,
            //we preserve the value of $this->exitCode if the value is -1 which
            //means that we've already extracted a meaningful exit code.
            $this->exitCode = isset($s['exitcode']) && $s['exitcode'] != -1 ?
                $s['exitcode'] :
                $this->exitCode;
        }

        if (isset($s['signaled']) && $s['signaled'] === true) {
            $this->status = static::STATUS_SIGNALED;
            $this->termSignal = isset($s['termsig']) ? $s['termsig'] : null;
        } elseif (isset($s['stopped']) && $s['stopped'] === true) {
            $this->status = static::STATUS_STOPPED;
            $this->stopSignal = isset($s['stopsig']) ? $s['stopsig'] : null;
        }
    }

    /**
     * Terminates a running process, but returns without waiting for result.
     *
     * @param int $signal POSIX compliant signal, see kill(2) for valid signals
     *
     * @return bool True if terminated.
     */
    public function terminate($signal = self::SIG_TERM)
    {
        $this->statusCheck();
        if ($this->status == static::STATUS_RUNNING) {
            proc_terminate($this->process, $signal);

            //change teh status to pending termination
            $this->status = static::STATUS_PENDING_TERMINATION;

            return true;
        }

        return false;
    }

    /**
     * Sends a signal to close a process. The reason we don't call proc_close() directly here is because
     * we don't ever want to block on any calls. Terminating has a similar effect but with no blocking.
     *
     * @return bool True if signal was sent successfully.
     */
    public function close()
    {
        return $this->terminate(self::SIG_TERM);
    }

    /**
     * Retrieve the process ID.
     *
     * @return int PID
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * Retrives the original command used to start this task.
     *
     * @return string The command.
     */
    public function getCmd()
    {
        return $this->taskSpec->getCommand();
    }

    /**
     * Retrieves the status on this task.
     *
     * @return int One of the STATUS_* constants.
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Retrieves the exit code if exited.
     *
     * @return int|null the exit code.
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * Retrieves the termination signal. Meaningful only if terminated by
     * a signal.
     *
     * @return int The termination signal.
     */
    public function getTermSignal()
    {
        return $this->termSignal;
    }

    /**
     * Retrieves the stop signal. Meaningful only if stopped by a signal.
     *
     * @return int The stop signal.
     */
    public function getStopSignal()
    {
        return $this->stopSignal;
    }

    /**
     * Checks if this is a daemon process.
     *
     * @return bool True if a daemon process.
     */
    public function isDaemon()
    {
        return $this->taskSpec->isDaemon();
    }

    /**
     * Retrieves the runtime in seconds if the process has started.
     *
     * @return int|null Runtime in seconds with microsecond precision
     *                  or null if not yet started.
     */
    public function getRuntime()
    {
        if ($this->startTime === null) {
            return;
        }

        return microtime(true) - $this->startTime;
    }

    /**
     * Retrieves the task spec.
     *
     * @return TaskSpecInterface
     */
    public function getTaskSpec()
    {
        return $this->taskSpec;
    }
}
