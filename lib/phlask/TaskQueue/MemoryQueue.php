<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

namespace phlask\TaskQueue;

use phlask\TaskQueueInterface;
use phlask\TaskSpecInterface;

/**
 * A simple in-memory queue
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */
class MemoryQueue implements TaskQueueInterface
{
    /**
     * Holds an array of TaskSpecInterface objects
     *
     * @var array
     */
    protected $queue = array();

    /**
     * Checks if any tasks exist.
     *
     * @return bool True if there are any tasks.
     */
    public function hasTasks()
    {
        return count($this->queue) > 0;
    }

    /**
     * Retrieves a number of remaining tasks in the queue.
     *
     * @return int The number of tasks (can be zero).
     */
    public function count()
    {
        return count($this->queue);
    }

    /**
     * Retrieve the next task off the queue.
     *
     * @return TaskSpecInterface The next task.
     */
    public function popTask()
    {
        if (empty($this->queue)) {
            return null;
        }

        return array_shift($this->queue);
    }

    /**
     * Add a task to the queue.
     *
     * @param  TaskSpecInterface $task The task to add.
     * @return null
     */
    public function pushTask(TaskSpecInterface $task)
    {
        $this->queue[] = $task;
    }
}
