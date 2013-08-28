<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * PHP version 5.4.16
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

namespace phlask;

/**
 * A simple interface queueing up tasks
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */
interface TaskQueueInterface
{
    /**
     * Checks if any tasks exist.
     *
     * @return bool True if there are any tasks.
     */
    public function hasTasks();

    /**
     * Retrieves a number of remaining tasks in the queue.
     *
     * @return int The number of tasks (can be zero).
     */
    public function count();

    /**
     * Retrieve the next task off the queue.
     *
     * @return TaskSpecInterface The next task.
     */
    public function popTask();

    /**
     * Add a task to the queue.
     *
     * @param  TaskSpecInterface $task The task to add.
     * @return null
     */
    public function pushTask(TaskSpecInterface $task);
}
