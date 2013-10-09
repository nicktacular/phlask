<?php

use phlask\TaskSpec\ShellRunnable;

class ShellRunnableTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover ShellRunnable::factory
     */
    public function testEmptyFactoryFail()
    {
        $task = ShellRunnable::factory([]);
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover ShellRunnable::factory
     */
    public function testFactoryFail()
    {
        $task = ShellRunnable::factory([
            'cmd' => 'ls',
            'args' => ['/Volumes']
        ]);
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover ShellRunnable::factory
     */
    public function testFactoryFailPartial()
    {
        $task = ShellRunnable::factory([
            'cmd' => 'ls',
            'name' => 'dir listing'
        ]);
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover ShellRunnable::factory
     */
    public function testFactoryFailPartial2()
    {
        $task = ShellRunnable::factory([
            'cmd' => 'ls',
            'cwd' => '/junk',
            'name' => 'invalid dir list'
        ]);
    }

    /**
     * @expectedException \phlask\Exception\InvalidArgumentException
     * @cover ShellRunnable::factory
     */
    public function testFactoryFailPartial3()
    {
        $task = ShellRunnable::factory([
            'cmd' => 'ls',
            'cwd' => '/junk'
        ]);
    }

    /**
     * @cover ShellRunnable::factory, ShellRunnable::trustExitCode, ShellRunnable::getEnv, ShellRunnable::getCwd, ShellRunnable::getName
     */
    public function testValidFactory()
    {
        $task = ShellRunnable::factory([
            'cmd' => 'ls',
            'cwd' => '/etc',
            'name' => 'listing of /etc'
        ]);

        $this->assertTrue($task->trustExitCode());
        $this->assertSame([], $task->getEnv());
        $this->assertSame('/etc', $task->getCwd());
        $this->assertSame('listing of /etc', $task->getName());
        $this->assertSame('ls', $task->getCommand());
        $this->assertTrue($task->isDaemon());
        $this->assertSame(0, $task->getTimeout());
    }

    /**
     * @cover ShellRunnable::factory, ShellRunnable::trustExitCode, ShellRunnable::getEnv, ShellRunnable::getCwd, ShellRunnable::getName
     */
    public function testValidFactoryArgs()
    {
        $task = ShellRunnable::factory([
            'cmd' => 'ls',
            'cwd' => '/',
            'name' => 'listing of /var',
            'args' => ['/var']
        ]);

        $this->assertTrue($task->trustExitCode());
        $this->assertSame([], $task->getEnv());
        $this->assertSame('/', $task->getCwd());
        $this->assertSame('listing of /var', $task->getName());
        $this->assertSame("ls '/var'", $task->getCommand());
        $this->assertTrue($task->isDaemon());
        $this->assertSame(0, $task->getTimeout());
    }
}