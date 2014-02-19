<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

use phlask\Runner;
use Mockery as m;
use phlask\TaskQueue\MemoryQueue;
use phlask\TaskSpec\ShellRunnable;
use phlask\TaskSpec\NullSleeperRunnable;
use phlask\Task;

class RunnerTest extends PHPUnit_Framework_TestCase
{
    private function createFileTasks($num)
    {
        $fixtureDir = dirname(dirname(__FILE__)) . '/fixtures';
        $queue = new MemoryQueue();
        $tracking = [];
        for ($i = 0; $i < $num; $i++) {
            $tmp = tempnam(sys_get_temp_dir(), __CLASS__);
            $queue->pushTask(ShellRunnable::factory(array(
                'cmd' => "bash fileWrite.sh $i $tmp $i",
                'cwd' => $fixtureDir,
                'name' => "task_$i",
            )));

            $tracking["task$i"] = ['i' => $i, 'file' => $tmp];
        }

        return array($queue, $tracking);
    }

    private function createNullTasks($num)
    {
        $queue = new MemoryQueue();
        for ($i = 0; $i < $num; $i++) {
            $queue->pushTask(NullSleeperRunnable::factory(array('sleep' => 1)));
        }

        return $queue;
    }

    public static function setUpBeforeClass()
    {
        if (!class_exists('MemLogger')) {
            require_once dirname(dirname(__FILE__)) . '/fixtures/MemLogger.php';
        }
    }

    /**
     * @expectedException phlask\Exception\InvalidArgumentException
     * @expectedExceptionMessage No task queue provided in 'tasks' key.
     */
    public function testEmptyFactory()
    {
        Runner::factory(array());
    }

    /**
     * @expectedException phlask\Exception\InvalidArgumentException
     * @expectedExceptionMessage The tasks class you provided (stdClass) does not implement TaskQueueInterface.
     */
    public function testInvalidTaskQueueFactory()
    {
        Runner::factory(array(
            'tasks' => new stdClass()
        ));
    }

    /**
     * @expectedException phlask\Exception\InvalidArgumentException
     * @expectedExceptionMessage You must provide a wait (in µs) and it must be a positive, non-zero integer.
     */
    public function testInvalidWaitTimeFactory()
    {
        Runner::factory(array(
            'tasks' => m::mock('phlask\TaskQueueInterface'),
            'wait' => -5
        ));
    }

    /**
     * @expectedException phlask\Exception\InvalidArgumentException
     * @expectedExceptionMessage The 'max_processes' must be specified as a positive integer.
     */
    public function testInvalidMaxProcessesFactory()
    {
        Runner::factory(array(
            'tasks' => m::mock('phlask\TaskQueueInterface'),
            'wait' => 20,
            'daemon' => true,
            'max_processes' => 0
        ));
    }

    public function testValidFactory()
    {
        $runner = Runner::factory(array(
            'tasks' => $queue = m::mock('phlask\TaskQueueInterface'),
            'wait' => 20,
            'daemon' => true,
            'max_processes' => 25,
            'logger' => $logger = m::mock('Psr\Log\AbstractLogger'),
        ));

        $this->assertInstanceOf('phlask\Runner', $runner, 'The runner must be a Runner');

        $this->assertAttributeSame($queue, 'tasks', $runner);

        $this->assertAttributeSame(20, 'wait', $runner);

        $this->assertAttributeSame(25, 'maxProcesses', $runner);

        $this->assertAttributeSame(true, 'daemonMode', $runner);

        $this->assertAttributeSame($logger, 'logger', $runner);

        $this->assertAttributeInstanceOf('SplObjectStorage', 'runningTasks', $runner);
    }

    public function testRunnerTasksActuallyRan()
    {
        $tasks = $this->createFileTasks(10);
        $runner = Runner::factory(array(
            'tasks' => $tasks[0],
            'wait' => 20,
            'daemon' => false,
            'max_processes' => $max = 20,
            'logger' => $logger = new MemLogger()
        ));

        $runner->run();

        foreach ($tasks[1] as $task)
        {
            //see what's in the file
            if (file_get_contents($task['file']) != $task['i']) {
                var_dump($logger->log);
                $this->fail("The process run failed. Expected \"{$task['i']}\" in the file {$task['file']}.");
            } else {
                unlink($task['file']);
            }
        }
    }

    public function testRunnerLimitedProcesses()
    {
        $tasks = $this->createNullTasks(10);
        $runner = Runner::factory(array(
            'tasks' => $tasks,
            'wait' => 20,
            'daemon' => false,
            'max_processes' => $max = 2,
            'logger' => $logger = new MemLogger()
        ));

        $runner->run();

        //inspect the logs to make sure that we only ever ran $max processes at a time
        foreach ($logger->log as $entry) {
            if (preg_match('/^Currently running tasks: (?<count>[0-9]+)$/', $entry, $matches)) {
                $this->assertLessThanOrEqual($max, $matches['count'], 'Wrong number of processes were running!');
            }
        }
    }

    public function testExitCodes()
    {
        $tasks = $this->createFileTasks($numOfTasks = 5);
        $runner = Runner::factory(array(
            'tasks' => $tasks[0],
            'wait' => 20,
            'daemon' => false,
            'max_processes' => 10,
            'logger' => $logger = new MemLogger()
        ));

        $runner->run();

        foreach ($tasks[1] as $task)
        {
            //see what's in the file
            if (file_get_contents($task['file']) != $task['i']) {
                $this->fail("The process run failed. Expected \"{$task['i']}\" in the file {$task['file']}.");
            } else {
                unlink($task['file']);
            }
        }

        //ensure that the exit codes were as expected
        $found = array();
        foreach ($logger->log as $entry) {
            if (preg_match('/^Task task_(?<task_id>[0-9]+) \([0-9]+\)[a-zA-Z ]+(?<code>[0-9]+)$/', $entry, $matches)) {
                $this->assertEquals($matches['task_id'], $matches['code']);
                $found[] = $matches['task_id'];
            }
        }

        if ($numOfTasks !== count($found)) {
            $this->fail('Did not find enough exit codes for how many procs were run');
        }
    }

    public function testExpiredTasksRun()
    {
        $tasks = new MemoryQueue();
        $tasks->pushTask(NullSleeperRunnable::factory(array(
            'sleep' => 10000000,//10 s
            'daemon' => false,
            'timeout' => 1
        )));

        $runner = Runner::factory(array(
            'tasks' => $tasks,
            'wait' => 20,
            'daemon' => false,
            'max_processes' => 1,
            'logger' => $logger = new MemLogger()
        ));

        $runner->run();

        //ensure that the task was signalled correctly
        foreach ($logger->log as $entry) {
            if (preg_match('/^Task .+ signaled with (?<sig>[0-9]+)/', $entry, $matches)) {
                $this->assertEquals(Task::SIG_TERM, $matches['sig']);
                $found = true;
            }
        }

        if (!isset($found)) {
            $this->fail('Did not find a termination signal message');
        }
    }
}
