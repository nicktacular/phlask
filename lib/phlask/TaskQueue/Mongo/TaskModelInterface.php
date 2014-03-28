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


use phlask\TaskSpecInterface;

/**
 * A simple MongoDB based queue: the Task model.
 *
 * @category Phlask
 * @package  TaskRunner
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */
interface TaskModelInterface
{
    /**
     * Get the name of the ID column. Usually this would just be '_id' but you can choose anything that matches
     * your schema.
     *
     * @return string The name of the id column.
     */
    public function getIdCol();

    /**
     * Get the name of the collection to use for locks.
     *
     * @return string
     */
    public function getCollection();

    /**
     * Retrieve an array that will be used to filter which tasks are returned off the queue. This could be used
     * for querying against a particular status field just for new tasks. An example could be ['status' => 'new'].
     *
     * @return array The filter array.
     */
    public function buildAvailableTaskFilter();

    /**
     * Retrieve an array that will be used for sorting. Anything that can be applied to the MongoCursor->sort()
     * method will work here. Note that an empty array can be returned if no sorting is necessary.
     *
     * @return array
     */
    public function buildAvailableTaskSort();

    /**
     * Update the data that was retrieved after taking off the queue and update it. This could be used for updating
     * a status field to indicate that this item has be removed from the available queue. Note that the entire updated
     * model should be returned here.
     *
     * @param array $model The model retrieved from the DB.
     *
     * @return array The updated model.
     */
    public function updateAfterPopped(array $model);

    /**
     * Create an actual task from the model in the DB.
     *
     * @param array $model The model retrieved from the DB.
     *
     * @return TaskSpecInterface The task to run.
     */
    public function createTaskFromModel(array $model);

    /**
     * A shortcut method to get the ID from the model.
     *
     * @param array $model The model retrieved from the DB.
     *
     * @return mixed
     */
    public function getIdFromModel(array $model);
}