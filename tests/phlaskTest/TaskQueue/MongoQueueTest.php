<?php
/**
 * A parallel processing library with a light footprint allowing
 * task and process management in PHP.
 *
 * @author   Nick Ilyin <nick.ilyin@gmail.com>
 * @license  http://opensource.org/licenses/MIT MIT
 * @link     http://github.com/nicktacular/phlask
 */

use phlask\TaskQueue\MongoQueue;
use \Mockery as m;

class MongoQueueTest extends PHPUnit_Framework_TestCase
{
    protected static $mongoDb = 'mongodb://localhost';
    protected static $db = '';
    protected static $skip = '';
    protected static $taskCollection = 'test_task';
    protected static $taskLockCollection = 'test_task_lock';

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::$db = 'testQueue' . uniqid();

        //check that the necessary extension is loaded
        if (!class_exists('MongoClient')) {
            self::$skip = 'Requires MongoClient >=1.4.';
            return;
        }

        //also make sure that there's a local mongo available
        try {
            $test = new MongoClient(self::$mongoDb);
            $test->connect();
        } catch (MongoCursorException $e) {
            self::$skip = 'Requires a MongoDB instance to test on:' . self::$mongoDb;
        }
    }

    public static function tearDownAfterClass()
    {
        if (empty(self::$skip)) {
            //delete test db
            $mongo = new MongoClient(self::$mongoDb);
            $mongo->dropDB(self::$db);
        }
        parent::tearDownAfterClass();
    }

    public function setUp()
    {
        if (self::$skip) {
            $this->markTestSkipped(self::$skip);
        }

        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        m::close();
        $this->cleanup();
    }

    private function cleanup()
    {
        $conn = new MongoClient(self::$mongoDb);
        $db = $conn->selectDB(self::$db);

        $db->dropCollection(self::$taskCollection);
        $db->dropCollection(self::$taskLockCollection);

        $conn->close(true);
    }

    private function getTaskModelMock()
    {
        $model = m::mock('\phlask\TaskQueue\Mongo\TaskModelInterface');
        $model->shouldReceive('getCollection')->times(1)->andReturn(self::$taskCollection);
        return $model;
    }

    private function getTaskLockModelMock()
    {
        $model = m::mock('\phlask\TaskQueue\Mongo\TaskLockModelInterface');
        $model->shouldReceive('getCollection')->times(1)->andReturn(self::$taskLockCollection);
        return $model;
    }

    private function fillQueueWithTasks($num, $status = 0)
    {
        $conn = new MongoClient(self::$mongoDb);
        $db = $conn->selectDB(self::$db);
        $col = $db->selectCollection(self::$taskCollection);

        $inserted = array();

        while ($num > 0) {
            $toInsert = array(
                'status' => $status,
                'date' => new MongoDate(),
                'arbitrary_text' => uniqid()
            );
            $col->insert($toInsert);
            $inserted[] = $toInsert;
            $num--;
        }

        $conn->close(true);

        return $inserted;
    }

    private function createLock($id)
    {
        $conn = new MongoClient(self::$mongoDb);
        $db = $conn->selectDB(self::$db);
        $col = $db->selectCollection(self::$taskLockCollection);

        $col->insert(array(
            '_id' => new MongoId($id),
            'date' => new MongoDate(),
            'mock_it_up' => true
        ));

        $conn->close(true);
    }

    private function removeLock($id)
    {
        $conn = new MongoClient(self::$mongoDb);
        $db = $conn->selectDB(self::$db);
        $col = $db->selectCollection(self::$taskLockCollection);

        $col->remove(array(
            '_id' => new MongoId($id)
        ));

        $conn->close(true);
    }

    /**
     * @cover MongoQueue::count, MongoQueue:hasTasks
     */
    public function testEmptyQueue()
    {
        $taskModel = $this->getTaskModelMock();
        $taskLockModel = $this->getTaskLockModelMock();

        // in our model, status == 0 means available for consumption
        $taskModel->shouldReceive('buildAvailableTaskFilter')->times(1)->andReturn(array(
            'status' => 0
        ));

        $queue = new MongoQueue(self::$mongoDb, self::$db, $taskModel, $taskLockModel);

        //no tasks yet
        $this->assertFalse($queue->hasTasks());
    }

    public function testQueueHasOneTask()
    {
        $taskModel = $this->getTaskModelMock();
        $taskLockModel = $this->getTaskLockModelMock();

        // in our model, status == 0 means available for consumption
        $taskModel->shouldReceive('buildAvailableTaskFilter')->times(4)->andReturn(array(
            'status' => 0
        ));
        $taskModel->shouldReceive('buildAvailableTaskSort')->times(1)->andReturn(array());

        $taskModel->shouldReceive('updateAfterPopped')->times(1)->andReturnUsing(function($model){
            $model['status'] = 1;
            return $model;
        });

        $taskModel->shouldReceive('getIdFromModel')->times(1)->andReturnUsing(function($model){
            return $model['_id'];
        });

        $taskModel->shouldReceive('createTaskFromModel')->times(1)->andReturnUsing(function(){
            return \phlask\TaskSpec\NullSleeperRunnable::factory();
        });

        $taskLockModel->shouldReceive('prepareInsert')->times(1)->andReturnUsing(function($id){
            return array('_id' => new MongoId($id), 'date' => new MongoDate());
        });

        $taskLockModel->shouldReceive('prepareDeleteQuery')->times(1)->andReturnUsing(function($id){
            return array('_id' => new MongoId($id));
        });

        $queue = new MongoQueue(self::$mongoDb, self::$db, $taskModel, $taskLockModel);

        $this->fillQueueWithTasks(1);

        $this->assertTrue($queue->hasTasks());
        $this->assertSame(1, $queue->count());
        $task = $queue->popTask();
        $this->assertInstanceOf('\phlask\TaskSpecInterface', $task);
    }

    public function testQueueHasTasksButNoAvailableTasks()
    {
        $taskModel = $this->getTaskModelMock();
        // in our model, status == 0 means available for consumption
        $taskModel->shouldReceive('buildAvailableTaskFilter')->times(1)->andReturn(array(
            'status' => 0
        ));

        // we don't expect any calls on this class
        $taskLockModel = $this->getTaskLockModelMock();

        $queue = new MongoQueue(self::$mongoDb, self::$db, $taskModel, $taskLockModel);

        //fill queue with all unavailable tasks
        $this->fillQueueWithTasks(5, 1);

        $this->assertNull($queue->popTask());
    }

    public function testQueueHasSomeAvailableTasks()
    {
        $taskModel = $this->getTaskModelMock();
        // in our model, status == 0 means available for consumption
        $taskModel->shouldReceive('buildAvailableTaskFilter')->times(3)->andReturn(array(
            'status' => 0
        ));
        $taskModel->shouldReceive('buildAvailableTaskSort')->times(1)->andReturn(array());

        $taskModel->shouldReceive('updateAfterPopped')->times(1)->andReturnUsing(function($model){
            $model['status'] = 1;
            return $model;
        });

        $taskModel->shouldReceive('getIdFromModel')->times(1)->andReturnUsing(function($model){
            return $model['_id'];
        });

        $taskModel->shouldReceive('createTaskFromModel')->times(1)->andReturnUsing(function(){
            return \phlask\TaskSpec\NullSleeperRunnable::factory();
        });

        $taskLockModel = $this->getTaskLockModelMock();

        $taskLockModel->shouldReceive('prepareInsert')->times(1)->andReturnUsing(function($id){
            return array('_id' => new MongoId($id), 'date' => new MongoDate());
        });

        $taskLockModel->shouldReceive('prepareDeleteQuery')->times(1)->andReturnUsing(function($id){
            return array('_id' => new MongoId($id));
        });

        $queue = new MongoQueue(self::$mongoDb, self::$db, $taskModel, $taskLockModel);

        //fill queue with all unavailable tasks
        $this->fillQueueWithTasks(5, 1);
        //and one available one
        $this->fillQueueWithTasks(1);

        $this->assertInstanceOf('\phlask\TaskSpecInterface', $queue->popTask());
        $this->assertNull($queue->popTask());
    }

    public function testCannotLockLockedItem()
    {
        //one available task
        $tasks = $this->fillQueueWithTasks(1);
        $task = current($tasks);
        $id = (string) $task['_id'];
        $this->createLock($id);

        $taskModel = $this->getTaskModelMock();
        // in our model, status == 0 means available for consumption
        $taskModel->shouldReceive('buildAvailableTaskFilter')->times(4)->andReturn(array(
            'status' => 0
        ));
        $taskModel->shouldReceive('buildAvailableTaskSort')->times(2)->andReturn(array());
        $taskModel->shouldReceive('updateAfterPopped')->times(1)->andReturnUsing(function($model){
            $model['status'] = 1;
            return $model;
        });
        $taskModel->shouldReceive('getIdFromModel')->times(2)->andReturnUsing(function($model){
            return $model['_id'];
        });
        $taskModel->shouldReceive('createTaskFromModel')->times(1)->andReturnUsing(function(){
            return \phlask\TaskSpec\NullSleeperRunnable::factory();
        });

        $taskLockModel = $this->getTaskLockModelMock();
        $taskLockModel->shouldReceive('prepareInsert')->times(2)->andReturnUsing(function($id){
            return array('_id' => new MongoId($id), 'date' => new MongoDate());
        });
        $taskLockModel->shouldReceive('prepareDeleteQuery')->times(1)->andReturnUsing(function($id){
            return array('_id' => new MongoId($id));
        });

        $queue = new MongoQueue(self::$mongoDb, self::$db, $taskModel, $taskLockModel);

        //initially, while the task is locked, we should NOT be able to remove it
        $this->assertNull($queue->popTask());

        $this->removeLock($id);

        //now that the lock is removed, we should be able to remove the task
        $task = $queue->popTask();
        $this->assertInstanceOf('\phlask\TaskSpecInterface', $task);
    }
}
