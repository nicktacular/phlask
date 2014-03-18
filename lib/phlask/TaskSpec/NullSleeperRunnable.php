<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

namespace phlask\TaskSpec;

use phlask\TaskSpecInterface;

class NullSleeperRunnable implements TaskSpecInterface
{
    /**
     * How long this process should sleep for.
     * @var int
     */
    protected $sleep;

    /**
     * Whether this is a daemon process..
     * @var bool
     */
    protected $daemon;

    /**
     * Timeout in seconds. Zero meaning indefinite.
     *
     * @var int
     */
    protected $timeout;

    /**
     * All new specs should created by means of a factory.
     *
     * @param array $config A list of configs (optional).
     *
     * @return TaskSpecInterface instance
     */
    public static function factory(array $config = array())
    {
        //just need to know how long to sleep
        if (!isset($config['sleep'])) {
            $config['sleep'] = 10000;//useconds
        }

        if (!isset($config['daemon'])) {
            $config['daemon'] = true;
        }

        if (!isset($config['timeout'])) {
            $config['timeout'] = 0;
        }

        return new static($config['sleep'], $config['daemon'], $config['timeout']);
    }

    protected function __construct($sleep, $daemon, $timeout)
    {
        $this->sleep    = $sleep;
        $this->daemon   = $daemon;
        $this->timeout  = $timeout;
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
        $php = exec('which php');
        return "$php -r \"usleep({$this->sleep});\"";
    }

    /**
     * Retrieves additional environment vars needed to run
     * this command.
     *
     * @return array|null An array of elements or null if none.
     */
    public function getEnv()
    {
        return array();
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
        return $this->daemon;
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
        return $this->timeout;
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
