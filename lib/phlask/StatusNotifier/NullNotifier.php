<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

namespace phlask\StatusNotifier;

use phlask\StatusNotifierInterface;
use phlask\Task;

/**
 * A Null notifier strategy.
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */
class NullNotifier implements StatusNotifierInterface
{
    /**
     * Updates the status of a task with an optional message.
     *
     * @param int    $status  One of the Task::STATUS_* constants.
     * @param Task   $task    The task that is used to update the status.
     * @param string $message An optional message to provide with this status update.
     *
     * @return bool True if the status was updated successfully.
     */
    public function updateStatus($status, Task $task, $message = null)
    {
        return null;
    }
}
