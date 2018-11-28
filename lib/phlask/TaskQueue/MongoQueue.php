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
use MongoClient;
use MongoException;
use MongoCursorException;
use MongoDB;
use MongoCollection;
use Closure;

/**
 * A simple MongoDB based queue.
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */
class MongoQueue implements TaskQueueInterface
{
    /**
     * The database that holds the collection for queues.
     *
     * @var MongoDB
     */
    protected $db;

    /**
     * The collection for the queue.
     *
     * @var MongoCollection
     */
    protected $col;

    /**
     * The lock collection.
     *
     * @var MongoCollection
     */
    protected $lock;

    /**
     * The model to use for the tasks.
     *
     * @var Mongo\TaskModelInterface
     */
    protected $taskModel;

    /**
     * The model to use for locking tasks while they're pulled off of the queue.
     *
     * @var Mongo\TaskLockModelInterface
     */
    protected $taskLockModel;

    /**
     * An optional error handler for MongoDB issues. If provided, it passes the current exception
     * as the first parameter to the closure.
     *
     * @var Closure
     */
    protected $errorHandler;

    /**
     * @param string                       $connString    The connection string.
     * @param string                       $db            The database name.
     * @param Mongo\TaskModelInterface     $taskModel     The task model.
     * @param Mongo\TaskLockModelInterface $taskLockModel The task lock model.
     * @param callable                     $errorHandler  (optional) A callable which should take one parameter (an exception).
     */
    public function __construct(
        $connString,
        $db,
        Mongo\TaskModelInterface $taskModel,
        Mongo\TaskLockModelInterface $taskLockModel,
        Closure $errorHandler = null
    ) {
        $m                      = new MongoClient($connString);
        $this->db               = $m->selectDB($db);
        $this->col              = $this->db->selectCollection($taskModel->getCollection());
        $this->lock             = $this->db->selectCollection($taskLockModel->getCollection());
        $this->taskLockModel    = $taskLockModel;
        $this->taskModel        = $taskModel;

        if ($errorHandler) {
            $this->errorHandler = $errorHandler;
        }
    }

    /**
     * Checks if any tasks exist.
     *
     * @return bool True if there are any tasks.
     */
    public function hasTasks()
    {
        return $this->count() > 0;
    }

    /**
     * Retrieves a number of remaining tasks in the queue.
     *
     * @return int The number of tasks (can be zero).
     */
    public function count()
    {
        try {
            return $this->col->count($this->taskModel->buildAvailableTaskFilter());
        } catch (MongoException $e) {
            $this->handleException($e);
        }

        return 0;
    }

    /**
     * Retrieve the next task off the queue.
     *
     * @return TaskSpecInterface|null The next task or null if none.
     */
    public function popTask()
    {
        try {
            if (!$this->hasTasks()) {
                return null;
            }

            //read task data from DB
            $tasks = $this->col
                ->find($this->taskModel->buildAvailableTaskFilter())
                ->sort($this->taskModel->buildAvailableTaskSort());

            //make sure that there are some tasks
            if (!$tasks || !$tasks->count()) {
                return null;
            }

            while ($task = $tasks->getNext()) {
                $id = $this->taskModel->getIdFromModel($task);
                //try to lock
                if (!$this->lock($id)) {
                    continue;
                }

                //update that we have taken this item off the queue and release the lock
                $updatedTask = $this->taskModel->updateAfterPopped($task);
                $this->col->save($updatedTask);
                $this->releaseLock($id);

                return $this->taskModel->createTaskFromModel($task);
            }
        } catch (MongoException $e) {
            $this->handleException($e);
        }

        return null;
    }

    /**
     * Attempts to lock an object.
     *
     * @param string $taskId The ID of the task we're locking.
     *
     * @return bool True if able to acquire a lock, false otherwise.
     */
    protected function lock($taskId)
    {
        try {
            $newLock = $this->taskLockModel->prepareInsert($taskId);
            $this->lock->insert(
                $newLock,
                array('w' => 1)//write must be acknowledged
            );

            return true;
        } catch (MongoCursorException $e) {
            //expected behavior for cursor failing due to existing lock
            return false;
        } catch (MongoException $e) {
            $this->handleException($e);
        }

        return false;
    }

    /**
     * Removes the lock from this a task.
     *
     * @param string $taskId The ID of the task to unlock.
     */
    protected function releaseLock($taskId)
    {
        try {
            $this->lock->remove(
                $this->taskLockModel->prepareDeleteQuery($taskId),
                array('w' => 1)
            );
        } catch (MongoException $e) {
            $this->handleException($e);
        }
    }

    /**
     * Passes the exception to the custom handler if it was provided.
     *
     * @param \Exception $e The exception to handle.
     */
    protected function handleException(\Exception $e)
    {
        if ($cb = $this->errorHandler) {
            $cb($e);
        }
    }
}
