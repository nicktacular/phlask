<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

namespace phlask\TaskQueue\Mongo;

/**
 * A simple MongoDB based queue: the Task lock model.
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */
interface TaskLockModelInterface
{
    /**
     * Get the name of the collection to use for locks.
     *
     * @return string
     */
    public function getCollection();

    /**
     * Prepare an array that will be the object used to create a lock document. Note that the resulting array
     * should not be allowed to be inserted more than once. An example of an array that would follow this behaviour
     * is ['_id' => $taskId, 'date' => new MongoDate] where trying to insert it twice would fail because '_id' must
     * be unique in a MongoDB. However, it need not be the '_id' field if there is another unique index prepared for
     * this collection.
     *
     * @param string $taskId The task ID we're trying to lock.
     *
     * @return array The object to insert as a document representing this lock.
     */
    public function prepareInsert($taskId);

    /**
     * An array which is sufficiently specific enough to delete the lock that was created earlier.
     * For example, if the '_id' field is used: ['_id' => $taskId]
     *
     * @param string $taskId The task ID we're trying to release the lock from.
     *
     * @return array The array query.
     */
    public function prepareDeleteQuery($taskId);
}
