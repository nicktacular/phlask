<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

use phlask\TaskSpecInterface;
use phlask\TaskSpec\PhpRunnable;
use phlask\TaskSpec\ShellRunnable;
use phlask\TaskQueue\MongoQueue;

class MongoQueueTest extends PHPUnit_Framework_TestCase
{
    public function setup()
    {
        //make sure we only run if MongoClient is available
        if (!class_exists('MongoClient')) {
            $this->markTestIncomplete('Requires MongoClient version 1.4 or greater');
        }
    }

    private function cleanupQueue(MongoQueue $queue)
    {
        while ($queue->hasTasks()) {
            $queue->popTask();
        }
    }

    public function testEmptyQueue()
    {
        $queue = new MongoQueue('mongodb://localhost', 'testQueue', 'queueCol');

        $this->assertSame(0, $queue->count(), 'The queue should be empty');
        $this->assertNull($queue->popTask(), 'The queue should have no tasks to give');
    }

    public function testQueueWithOneItem()
    {
        $queue = new MongoQueue('mongodb://localhost', 'testQueue', 'queueCol');
        $queue->pushTask(ShellRunnable::factory(array(
            'cmd' => 'ls',
            'cwd' => '/',
            'name' => 'dummy listing command'
        )));

        $this->assertSame(1, $queue->count(), 'The queue should have exactly 1 item');
        $this->assertTrue($queue->hasTasks(), 'hasTasks() should return true');

        $task = $queue->popTask();

        $this->assertSame(0, $queue->count(), 'The queue should have no items after pop');
        $this->assertFalse($queue->hasTasks(), 'hasTasks() should return false after pop');
        $this->assertTrue($task instanceof TaskSpecInterface, 'Should be a task spec interface');
        $this->assertNull($queue->popTask(), 'There should be no tasks left');

        //cleanup
        $this->cleanupQueue($queue);
    }

    public function testQueueWithMultipleItems()
    {
        $queue = new MongoQueue('mongodb://localhost', 'testQueue', 'queueCol');
        for ($i = 0; $i < 5; $i++) {
            $queue->pushTask(ShellRunnable::factory(array(
                'cmd' => "echo $i",
                'cwd' => '/',
                'name' => "echo $i"
            )));
        }

        $this->assertSame(5, $queue->count(), 'Should contain 5 items');
        $this->assertTrue($queue->hasTasks(), 'Should have tasks');

        $task = $queue->popTask();
        $name = $task->getName();

        $this->assertSame(4, $queue->count(), 'Should contain 4 items');
        $this->assertSame("echo 0", $name, "The first task to come off should be 'echo 0' (the first one we added) FIFO YO!");

        $task = $queue->popTask();
        $name = $task->getName();

        $this->assertSame(3, $queue->count(), 'Should contain 3 items');
        $this->assertSame("echo 1", $name, "The second task to come off should be 'echo 1' (the second one we added) FIFO YO!");

        //cleanup
        $this->cleanupQueue($queue);
    }
}
