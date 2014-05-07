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
        $queue = new MemoryQueue();
        $tracking = array();
        for ($i = 0; $i < $num; $i++) {
            $tmp = tempnam(sys_get_temp_dir(), __CLASS__);
            $queue->pushTask(ShellRunnable::factory(array(
                'cmd' => "bash fileWrite.sh $i $tmp $i",
                'cwd' => FIXTURES_DIR,
                'name' => "task_$i",
            )));

            $tracking["task$i"] = array('i' => $i, 'file' => $tmp);
        }

        return array($queue, $tracking);
    }

    private function createTrappableScriptTasks($num)
    {
        $queue = new MemoryQueue();
        $tracking = array();
        for ($i = 0; $i < $num; $i++) {
            //$tmp = tempnam(sys_get_temp_dir(), __CLASS__);
            $tmp = sys_get_temp_dir() . '/' . __CLASS__;
            $queue->pushTask(ShellRunnable::factory(array(
                'cmd' => "bash trappableScript.sh ${tmp}${i} 30",
                'cwd' => FIXTURES_DIR,
                'name' => "task_$i",
            )));

            $tracking["task$i"] = array('i' => $i, 'file' => $tmp.$i);
        }

        return array($queue, $tracking);
    }

    private function createNullTasks($num, $sleep = 1)
    {
        $queue = new MemoryQueue();
        for ($i = 0; $i < $num; $i++) {
            $queue->pushTask(NullSleeperRunnable::factory(array('sleep' => $sleep)));
        }

        return $queue;
    }

    public static function setUpBeforeClass()
    {
        if (!class_exists('MemLogger')) {
            require_once FIXTURES_DIR . '/MemLogger.php';
        }
    }

    public function tearDown()
    {
        m::close();
        parent::tearDown();
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
            if (preg_match('/^Task task_(?<task_id>[0-9]+) \([0-9]+\)[a-zA-Z ]+\(exit: (?<code>[0-9]+)\)$/', $entry, $matches)) {
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
            $this->fail('Did not find a termination signal message. Log: ' . print_r($logger->log, true));
        }
    }

    public function testGlobalTerminationSent()
    {
        $queue = $this->createTrappableScriptTasks($numOfTasks = 5);
        $runner = Runner::factory(array(
            'tasks' => $queue[0],
            'wait' => 20,
            'daemon' => true,
            'max_processes' => 10,
            'logger' => $logger = new MemLogger()
        ));

        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('Need pcntl and posix modules to test global termination');
            return;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('Process forking failed with pcntl_fork.');
            return;
        }

        if ($pid) {
            //parent
            //issue kill signal after we've given the process a chance to start running the tasks
            usleep(500000);
            exec('kill -TERM ' . $pid);
            if (!pcntl_waitpid($pid, $status)) {
                $this->fail("Failed to wait for termination with pcntl_waitpid");
                return;
            }

            $termSig = pcntl_wtermsig($status);
            if ($termSig != 0) {
                $this->fail("Not signalled properly! Expected 0, got " . $termSig);
                return;
            }

            //wait until the first file is available or quit
            $first = reset($queue[1]);
            $start = microtime(true);
            $max = 5;//up to 5 seconds
            while (!file_exists($first['file']) && $start + $max > microtime(true)) {
                usleep(250000);
            }

            foreach ($queue[1] as $taskKey => $taskSpec) {
                if (!file_exists($taskSpec['file'])) {
                    $this->fail("Expected $taskKey to create {$taskSpec['file']} but that file is absent.");
                    return;
                }

                $contents = trim(file_get_contents($taskSpec['file']));
                $this->assertSame(
                    'SIGTERM',
                    $contents,
                    "Expected $taskKey to create {$taskSpec['file']} with 'SIGTERM'. Actual contents: '$contents'"
                );
                unlink($taskSpec['file']);
            }

        } else {
            //child
            ob_start();
            $runner->run();
            ob_end_clean();
            exit(0);
        }
    }

    public function testStatusChecks()
    {
        $statusNotifier = m::mock('phlask\StatusNotifierInterface');
        $status = array();
        $statusNotifier->shouldReceive('updateStatus')
            ->andReturnUsing(function($code, $task, $message = null) use (&$status) {
                $status[] = array($code, $task, $message);
            })->times(10);
        $tasks = $this->createNullTasks(10);
        $runner = Runner::factory(array(
            'tasks' => $tasks,
            'wait' => 20,
            'daemon' => false,
            'max_processes' => $max = 2,
            'logger' => $logger = new MemLogger(),
            'status_notifier' => $statusNotifier
        ));

        $runner->run();

        foreach ($status as $s) {
            $this->assertInternalType('string', $s[2]);
            $this->assertInternalType('int', $s[0]);
            $this->assertSame(Task::STATUS_COMPLETE, $s[0]);
            $this->assertInstanceOf('\phlask\Task', $s[1]);
        }
    }
}
