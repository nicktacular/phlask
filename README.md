phlask
======

A task runner that can be run in a distributed environment, consuming tasks off a queue and running the tasks in parallel on separate processes. None of the processes will affect each other, thereby allowing any task to be run in virtually any language with the ability to continue running tasks even when some (or all tasks) fail.

Currently queues are available for [MongoDB](http://mongodb.org) and [RabbitMQ](http://rabbitmq.com). Other queue interfaces are being developed for Redis, Amazon SQS, and MySQL.

## How to use this?

```php
$runner = \phlask\Runner::factory(array(
    'tasks'             => new \phlask\TaskQueue\MongoQueue($host, $db, $collection),
    'wait'              => 20,
    'daemon'            => false,
    'max_processes'     => 10
));

$runner->run();
```

In the simplest case, a `\phlask\Runner` instance is created that will read tasks from a MongoDB queue and run them until all the tasks have run and exit. Alternatively, the application can be run as a daemon in the background by setting `daemon` config as `false` in the factory. The `Runner` is made to run in the background in this manner, consuming tasks as soon as they are made available on the queue.

## Creating jobs

Any possible job can be created as long as the job follows the `\phlask\TaskSpecInterface` which defines some methods that are necessary to determine what it means to "run a job". This concept of a job is deliberately abstract to allow the user of this library to write their own task specifications that can be run as soon as they're placed on a stack by any host that is aware of the queue.

A simple example of what a task is can be found in `\phlask\TaskSpec\PhpRunnable` which can be created in this way. Suppose you want to run a PHP script located in this file: `/home/me/file.php`. You could set up your task like so:

```php
$task = \phlask\TaskSpec\PhpRunnable::factory(array(
    'file' => '/home/me/file.php',
    'php' => '/usr/bin/php'
));
```

It's that simple. You could also pass additional parameters by specifying `args` as a config.

Now, you need to add to some queue. Suppose you're using a MongoDB queue. It's fairly straightforward. Simply push the task you just created on to the queue:

```php
$queue = new \phlask\TaskQueue\MongoQueue('mongodb://127.0.0.1', 'db', 'queue');
$queue->pushTask($task);
```

That's all you need to do for adding a job to a queue.

## Running jobs

Running jobs requires starting the `Runner` in some way, either to run a finite number of tasks or to run as a daemon in the background, listening to the queue for new tasks to run. There are many different ways of creating a daemon script. You can read more about [creating daemons](daemon.md).

In order to create a functioning `Runner`, we need to know about the task queue and some basic settings to start.

```php
$runner = \phlask\Runner::factory(array(
    'tasks'             => new \phlask\TaskQueue\MongoQueue('mongodb://127.0.0.1', 'myDb', 'queue'),
    'wait'              => 20,//milliseconds for waiting for new tasks
    'daemon'            => true,//run in the background
    'max_processes'     => 10//maximum parallel processes
));

$runner->run();
```

The `tasks` config points to an instance of the queue. It can be any queue that implements the `phlask\TaskQueueInterface`. In this example we've chosen MongoDB on the localhost. The `wait` config indicates how many milliseconds we should wait for when there are no tasks on the queue. It is recommended to set `max_processes` to prevent too many processes from running in parallel if your task queue fills up with many jobs. The `daemon` flag will let the `Runner` run in daemon mode.



