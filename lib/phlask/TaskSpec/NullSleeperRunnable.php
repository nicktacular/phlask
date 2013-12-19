<?php

namespace phlask\TaskSpec;

use phlask\Exception;
use phlask\TaskSpecInterface;

class NullSleeperRunnable implements TaskSpecInterface
{
    /**
     * How long this process should sleep for.
     * @var int
     */
    protected $sleep;

    /**
     * All new specs should created by means of a factory.
     *
     * @param array $config A list of configs (optional).
     *
     * @return TaskSpecInterface instance
     */
    public static function factory(array $config = [])
    {
        //just need to know how long to sleep
        if (!isset($config['sleep'])) {
            $config['sleep'] = 10000;//useconds
        }

        return new static($config['sleep']);
    }

    protected function __construct($sleep)
    {
        $this->sleep = $sleep;
    }

    /**
     * Indicates whether the exit code can be trusted for
     * raising error levels after execution completes.
     *
     * @return bool True if trustworthy exit codes.
     */
    public function trustExitCode()
    {
        return true;
    }

    /**
     * Retrieves the command to execute.
     *
     * @return string The command to run.
     */
    public function getCommand()
    {
        return "/usr/bin/php -r \"usleep({$this->sleep});\"";
    }

    /**
     * Retrieves additional environment vars needed to run
     * this command.
     *
     * @return array|null An array of elements or null if none.
     */
    public function getEnv()
    {
        return [];
    }

    /**
     * Sets up the starting working directory.
     *
     * @return string The working directory.
     */
    public function getCwd()
    {
        return "/";
    }

    /**
     * Tells whether or not this process should be allowed to
     * run in the background indefinitely. This is used to
     * start up daemon processes that can run indefinitely.
     *
     * @return bool True if a daemon process.
     */
    public function isDaemon()
    {
        return true;
    }

    /**
     * Sets a time limit on this process for running in milliseconds.
     * Note that without realtime systems this value will always be
     * exceeded since there's no way to precisely stop at a particular
     * time. For example, setting 1000 may result in the process running
     * 1023 milliseconds.
     *
     * @return int The timeout in millisecods. Zero indicates no timeout. Note
     *             that this value is ignored if isDaemon() returns true.
     */
    public function getTimeout()
    {
        return 0;
    }

    /**
     * Retrieves a string name for the task.
     *
     * @return string
     */
    public function getName()
    {
        return "usleep({$this->sleep})";
    }
}
