<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

use phlask\TaskSpec\PhpRunnable;
use phlask\Task;

class PhpRunnableTest extends PHPUnit_Framework_TestCase
{
    protected static $simpleScriptFixture;
    protected static $simpleFailingScriptFixture;
    protected static $phpExec;

    public static function setUpBeforeClass()
    {
        self::$simpleScriptFixture = FIXTURES_DIR . '/SimplePhpScript.php';
        self::$simpleFailingScriptFixture = FIXTURES_DIR . '/SimpleFatalError.php';

        self::$phpExec = exec('which php');
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testEmptyFactoryFail()
    {
        $task = PhpRunnable::factory(array());
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testFactoryFail()
    {
        $task = PhpRunnable::factory(array(
            'php' => 'php',
            'file' => 'blah'
        ));
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testFactoryFailPartial()
    {
        $task = PhpRunnable::factory(array(
            'php' => 'junk',
            'file' => self::$simpleScriptFixture
        ));
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testFactoryFailPartial2()
    {
        $task = PhpRunnable::factory(array(
            'file' => self::$simpleScriptFixture
        ));
    }

    /**
     * @cover PhpRunnable::factory, PhpRunnable::trustExitCode, PhpRunnable::getEnv, PhpRunnable::getCwd, PhpRunnable::getName
     */
    public function testValidFactory()
    {
        $task = PhpRunnable::factory(array(
            'php' => self::$phpExec,
            'file' => self::$simpleScriptFixture
        ));

        $this->assertTrue($task->trustExitCode());
        $this->assertSame(array(), $task->getEnv());
        $this->assertSame(dirname(self::$simpleScriptFixture), $task->getCwd());
        $this->assertSame(basename(self::$simpleScriptFixture, '.php'), $task->getName());
        $this->assertSame(self::$phpExec . ' -f ' . escapeshellarg(self::$simpleScriptFixture), $task->getCommand());
        $this->assertTrue($task->isDaemon());
        $this->assertSame(0, $task->getTimeout());
    }

    /**
     * @cover PhpRunnable::factory, PhpRunnable::trustExitCode, PhpRunnable::getEnv, PhpRunnable::getCwd, PhpRunnable::getName
     */
    public function testValidFactoryArgs()
    {
        $task = PhpRunnable::factory(array(
            'php' => self::$phpExec,
            'file' => self::$simpleScriptFixture,
            'args' => array('a','2','z')
        ));

        $this->assertTrue($task->trustExitCode());
        $this->assertSame(array(), $task->getEnv());
        $this->assertSame(dirname(self::$simpleScriptFixture), $task->getCwd());
        $this->assertSame(basename(self::$simpleScriptFixture, '.php'), $task->getName());
        $this->assertSame(
            self::$phpExec . ' -f '
            . escapeshellarg(self::$simpleScriptFixture)
            . " 'a' '2' 'z'", $task->getCommand()
        );
        $this->assertTrue($task->isDaemon());
        $this->assertSame(0, $task->getTimeout());
    }

    public function testRunWithFailure()
    {
        $taskSpec = PhpRunnable::factory(array(
            'php' => self::$phpExec,
            'file' => self::$simpleFailingScriptFixture
        ));

        $task = Task::factory($taskSpec);
        $task->run();

        //run for a certain amount of time OR until the task status is complete
        $timeout = 0.5;//s
        $start = microtime(true);
        while (microtime(true) - $start < $timeout) {
            $task->statusCheck();
            $status = $task->getStatus();
            if ($status == Task::STATUS_COMPLETE) {
                //what was the signal?
                $this->assertSame(255, $task->getExitCode());
                $this->assertNull($task->getStopSignal());
                $this->assertNull($task->getTermSignal());
                return;
            }
        }

        $this->fail("This failure task ran out of time.");
    }

    public function testRunWithSuccess()
    {
        $taskSpec = PhpRunnable::factory(array(
            'php' => self::$phpExec,
            'file' => self::$simpleScriptFixture
        ));

        $task = Task::factory($taskSpec);
        $task->run();

        //run for a certain amount of time OR until the task status is complete
        $timeout = 0.5;//s
        $start = microtime(true);
        while (microtime(true) - $start < $timeout) {
            $task->statusCheck();
            $status = $task->getStatus();
            if ($status == Task::STATUS_COMPLETE) {
                //what was the signal?
                $this->assertSame(200, $task->getExitCode());
                $this->assertNull($task->getStopSignal());
                $this->assertNull($task->getTermSignal());
                return;
            }
        }

        $this->fail("This failure task ran out of time.");
    }

    public function testRunWithTermSignal()
    {
        $taskSpec = PhpRunnable::factory(array(
            'php' => self::$phpExec,
            'file' => self::$simpleScriptFixture
        ));

        $task = Task::factory($taskSpec);
        $task->run();
        $task->terminate(Task::SIG_ALRM);

        //run for a certain amount of time OR until the task status is complete
        $timeout = 0.5;//s
        $start = microtime(true);
        while (microtime(true) - $start < $timeout) {
            $task->statusCheck();
            $status = $task->getStatus();
            if ($status == Task::STATUS_COMPLETE) {
                //what was the signal?
                $this->assertNull($task->getExitCode());
                $this->assertNull($task->getStopSignal());
                $this->assertSame(Task::SIG_ALRM, $task->getTermSignal());
                return;
            }
        }

        $this->fail("This failure task ran out of time.");
    }

    public function testRunWithStopSignal()
    {
        $taskSpec = PhpRunnable::factory(array(
            'php' => self::$phpExec,
            'file' => self::$simpleScriptFixture
        ));

        $task = Task::factory($taskSpec);
        $task->run();
        $task->terminate(Task::SIG_HUP);

        //run for a certain amount of time OR until the task status is complete
        $timeout = 0.5;//s
        $start = microtime(true);
        while (microtime(true) - $start < $timeout) {
            $task->statusCheck();
            $status = $task->getStatus();
            if ($status == Task::STATUS_COMPLETE) {
                //what was the signal?
                $this->assertNull($task->getExitCode());
                $this->assertNull($task->getStopSignal());
                $this->assertSame(Task::SIG_HUP, $task->getTermSignal());
                return;
            }
        }

        $this->fail("This failure task ran out of time.");
    }
}
