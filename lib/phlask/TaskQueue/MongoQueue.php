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

namespace phlask\TaskQueue;

use phlask\TaskQueueInterface;
use phlask\TaskSpecInterface;
use MongoClient;
use MongoDate;

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
     * The constructor.
     */
    public function __construct($connString, $db, $collection)
    {
        $m         = new MongoClient($connString);
        $this->db  = $m->selectDB($db);
        $this->col = $this->db->selectCollection($collection);
    }
    /**
     * Checks if any tasks exist.
     *
     * @return bool True if there are any tasks.
     */
    public function hasTasks()
    {
        return $this->col->count() > 0;
    }

    /**
     * Retrieves a number of remaining tasks in the queue.
     *
     * @return int The number of tasks (can be zero).
     */
    public function count()
    {
        return $this->col->count();
    }

    /**
     * Retrieve the next task off the queue.
     *
     * @return TaskSpecInterface The next task.
     */
    public function popTask()
    {
        if (!$this->hasTasks()) {
            return null;
        }

        //read task data from DB
        $task = $this->col
            ->find()
            ->sort(['date' => 1])
            ->limit(1)
            ->getNext();
        $id   = $task['_id'];
        $task = unserialize($task['task']);

        //now delete
        $this->col->remove(['_id' => $id]);

        return $task;
    }

    /**
     * Add a task to the queue.
     *
     * @param  TaskSpecInterface $task The task to add.
     * @return null
     */
    public function pushTask(TaskSpecInterface $task)
    {
        $this->col->insert([
            'date' => new MongoDate,
            'task' => serialize($task)
        ]);
    }
}
