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
use phlask\Exception;
use phlask\TaskSpecInterface;

class PhpRunnable implements TaskSpecInterface
{
    /**
     * The full path to the PHP file that we wish to run
     *
     * @var string
     */
    protected $file;

    /**
     * The PHP executable.
     *
     * @var string
     */
    protected $php;

    /**
     * Arguments to be passed to the file during run.
     *
     * @var array
     */
    protected $args;

    /**
     * All new specs should created by means of a factory.
     *
     * @param array $config A list of configs (optional).
     *
     * @return TaskSpecInterface instance
     *
     * @throws Exception\InvalidArgumentException When a required config parameter is invalid (or not passed).
     */
    public static function factory(array $config = array())
    {
        if (!isset($config['file'])) {
            throw new Exception\InvalidArgumentException("No php file specified in config");
        }

        if (!is_readable($config['file'])) {
            throw new Exception\InvalidArgumentException("The file {$config['file']} isn't readable.");
        }

        if (!isset($config['php'])) {
            throw new Exception\InvalidArgumentException("No php exec path specified");
        }

        if (!is_executable($config['php'])) {
            throw new Exception\InvalidArgumentException("The php exec {$config['php']} isn't executable");
        }

        return new self($config['file'], $config['php'], isset($config['args']) ? $config['args'] : array());
    }

    protected function __construct($file, $php, array $args = array())
    {
        $this->file = $file;
        $this->php  = $php;
        $this->args = $args;
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
     * @todo Is this relative to what? PATH?
     *
     * @return string The command to run.
     */
    public function getCommand()
    {
        $cmd = $this->php . ' -f ' . escapeshellarg($this->file);

        if (count($this->args)) {
            foreach ($this->args as $arg) {
                $cmd .= ' ' . escapeshellarg($arg);
            }
        }

        return trim($cmd);
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
        return dirname($this->file);
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
        return basename($this->file, '.php');
    }
}
