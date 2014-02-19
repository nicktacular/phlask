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
use phlask\TaskSpec\ShellRunnable;
use phlask\TaskQueue\MemoryQueue;

class MemoryQueueTest extends PHPUnit_Framework_TestCase
{
    public function testEmptyQueue()
    {
        $queue = new MemoryQueue;

        $this->assertSame(0, $queue->count(), 'The queue should be empty');
        $this->assertNull($queue->popTask(), 'The queue should have no tasks to give');
    }

    public function testQueueWithOneItem()
    {
        $queue = new MemoryQueue;
        $queue->pushTask(ShellRunnable::factory([
            'cmd' => 'ls',
            'cwd' => '/',
            'name' => 'dummy listing command'
        ]));

        $this->assertSame(1, $queue->count(), 'The queue should have exactly 1 item');
        $this->assertTrue($queue->hasTasks(), 'hasTasks() should return true');

        $task = $queue->popTask();

        $this->assertSame(0, $queue->count(), 'The queue should have no items after pop');
        $this->assertFalse($queue->hasTasks(), 'hasTasks() should return false after pop');
        $this->assertTrue($task instanceof TaskSpecInterface, 'Should be a task spec interface');
        $this->assertNull($queue->popTask(), 'There should be no tasks left');
    }

    public function testQueueWithMultipleItems()
    {
        $queue = new MemoryQueue;
        for ($i = 0; $i < 5; $i++) {
            $queue->pushTask(ShellRunnable::factory([
                'cmd' => "echo $i",
                'cwd' => '/',
                'name' => "echo $i"
            ]));
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
    }
}
