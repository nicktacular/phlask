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

class PhpRunnableTest extends PHPUnit_Framework_TestCase
{
    protected static $simpleScriptFixture;
    protected static $phpExec;

    public static function setUpBeforeClass()
    {
        self::$simpleScriptFixture = FIXTURES_DIR . '/SimplePhpScript.php';

        self::$phpExec = exec('which php');
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testEmptyFactoryFail()
    {
        $task = PhpRunnable::factory([]);
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testFactoryFail()
    {
        $task = PhpRunnable::factory([
            'php' => 'php',
            'file' => 'blah'
        ]);
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testFactoryFailPartial()
    {
        $task = PhpRunnable::factory([
            'php' => 'junk',
            'file' => self::$simpleScriptFixture
        ]);
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover PhpRunnable::factory
     */
    public function testFactoryFailPartial2()
    {
        $task = PhpRunnable::factory([
            'file' => self::$simpleScriptFixture
        ]);
    }

    /**
     * @cover PhpRunnable::factory, PhpRunnable::trustExitCode, PhpRunnable::getEnv, PhpRunnable::getCwd, PhpRunnable::getName
     */
    public function testValidFactory()
    {
        $task = PhpRunnable::factory([
            'php' => self::$phpExec,
            'file' => self::$simpleScriptFixture
        ]);

        $this->assertTrue($task->trustExitCode());
        $this->assertSame([], $task->getEnv());
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
        $task = PhpRunnable::factory([
            'php' => self::$phpExec,
            'file' => self::$simpleScriptFixture,
            'args' => ['a','2','z']
        ]);

        $this->assertTrue($task->trustExitCode());
        $this->assertSame([], $task->getEnv());
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
}
