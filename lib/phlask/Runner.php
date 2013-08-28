<?php

namespace phlask;

use SplObjectStorage;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

class Runner
{
    /**
     * A hash of running tasks.
     *
     * @var SplObjectStorage Hash of resources opened by proc_open().
     */
    protected $runningTasks;

    /**
     * Holds the task queue. These are tasks waiting to be run.
     *
     * @var TaskQueueInterface
     */
    protected $tasks;

    /**
     * Indicates whether we're running as a daemon. This means that
     * this process never exits. It just sits and waits for the task
     * queue to respond with tasks. Setting to false means that this
     * process will exit once the task queue is empty.
     *
     * @var boolean
     */
    protected $daemonMode = true;

    /**
     * Verbose logging.
     *
     * @var boolean
     */
    protected $verbose = false;

    /**
     * How many µs to wait between sampling the running processes.
     *
     * @var int
     */
    protected $wait = 0;

    /**
     * Used for logging.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Creation of a runner instance.
     *
     * @param array $config The configuration for this instance.
     *
     * @return Runner An instance which can be activated by calling ->run().
     */
    public static function factory(array $config = [])
    {
        if (!isset($config['tasks'])) {
            throw new Exception\InvalidArgumentException("No task queue provided in 'tasks' key.");
        }

        if (!$config['tasks'] instanceof TaskQueueInterface) {
            throw new Exception\InvalidTaskQueueException(
                "The tasks class you provided (" . get_class($config['tasks'])
                . ") does not implement TaskQueueInterface."
            );
        }

        if (!isset($config['wait']) || !is_int($config['wait']) || $config['wait'] < 1) {
            throw new Exception\InvalidArgumentException(
                "You must provide a wait (in µs) and it must be a positive, non-zero integer."
            );
        }

        if (!isset($config['daemon'])) {
            $config['daemon'] = true;
        } else {
            $config['daemon'] = (bool) $config['daemon'];
        }

        //verbosity level
        if (isset($config['verbose']) && $config['verbose'] == true) {
            $this->verbose = true;
        }

        if (isset($config['logger']) && $config['logger'] instanceof LoggerInterface) {
            $logger = $config['logger'];
        } else {
            $logger = new NullLogger;
        }

        //php configs
        set_time_limit(0);

        return new self($config['tasks'], $config['daemon'], $config['wait'], $logger);
    }

    /**
     * Creates a runnable instance.
     *
     * @param TaskQueueInterface $tasks The queue that will be used to extract
     *                                  and run tasks for this instance.
     * @param bool $daemonMode If set to true, the instance will continue
     *                                  to wait even after there are no tasks in the
     *                                  queue, thereby allowing a perpetual process
     *                                  to run and act on items in the queue when
     *                                  they appear (eventually).
     * @param int             $wait   The number of µs to wait to sample a process.
     * @param LoggerInterface $logger A logger to output information about what's
     *                                  going on in this world.
     */
    public function __construct(
        TaskQueueInterface $tasks,
        $daemonMode,
        $wait,
        LoggerInterface $logger
    ) {
        $this->tasks        = $tasks;
        $this->daemonMode   = $daemonMode;
        $this->wait         = $wait;
        $this->runningTasks = new SplObjectStorage;
        $this->logger       = $logger;
    }

    //@todo catch the exceptions necessary in this method
    public function run()
    {
        while (1) {
            while ($this->tasks->hasTasks()) {
                $this->logger->info("Have " . $this->tasks->count() . ' tasks to start');
                //pop a task, init and run
                $taskSpec = $this->tasks->popTask();
                $task = Task::factory($taskSpec);
                $this->runningTasks->attach($task);
                $task->run();//@throws Exception\ExecutionException
                $this->logger->info($task->getTaskSpec()->getName() . '(' . $task->getPid() . ')');
            }

            //sleep so we poll later
            usleep($this->wait);

            //log message of running tasks
            $this->logger->info("Currently running tasks: " . $this->runningTasks->count());

            //check the status of the running tasks
            foreach ($this->runningTasks as $task) {
                $task->statusCheck();

                if ($task->getStatus() == Task::STATUS_RUNNING) {
                    //if it's not a daemon process, terminate if overtime
                    if (!$task->isDaemon() && $task->getTaskSpec()->getTimeout()) {
                        $max = $task->getTaskSpec()->getTimeout();
                        $run = $task->getRuntime();
                        if ($run > $max) {
                            $this->logger->warning(
                                'Terminating task ' . $task->getTaskSpec()->getName()
                                . ' for exceeding max runtime limit of ' . $max
                            );
                            $task->terminate();
                        }
                    }
                } elseif ($task->getStatus() == Task::STATUS_SIGNALED) {
                    $this->logger->info(
                        'Task ' . $task->getTaskSpec()->getName() . '(' . $task->getPid() . ') terminated with signal '
                        . $task->getTermSignal() . '. '
                        . ($task->getExitCode() !== null ? ' Exit code: ' . $task->getExitCode() : '')
                    );
                    $this->runningTasks->detach($task);
                } elseif ($task->getStatus() == Task::STATUS_STOPPED) {
                    $this->logger->info(
                        'Task ' . $task->getTaskSpec()->getName() . '(' . $task->getPid() . ') stopped with signal '
                        . $task->getStopSignal() . '. '
                        . ($task->getExitCode() !== null ? ' Exit code: ' . $task->getExitCode() : '')
                    );
                    $this->runningTasks->detach($task);
                } elseif ($task->getStatus() == Task::STATUS_COMPLETE) {
                    $this->logger->info(
                        'Task ' . $task->getTaskSpec()->getName() . '(' . $task->getPid() . ') is complete.'
                        . ($task->getExitCode() !== null ? ' Exit code: ' . $task->getExitCode() : '')
                    );
                    $this->runningTasks->detach($task);
                }
            }

            //only continue if in daemon mode
            if (!$this->runningTasks->count() && !$this->daemonMode) {
                break;
            }
        }
    }
}
