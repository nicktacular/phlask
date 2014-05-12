<?php

use \phlask\TaskSpec\ShellRunnable;
use \phlask\TaskQueue\MemoryQueue;
use \phlask\Runner;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$queue = new MemoryQueue();
$cwd = __DIR__ . '/fixtures';
$script = 'trappableScript.sh';

$queue->pushTask(ShellRunnable::factory(array(
    'cmd' => "bash $script ../out1 30",
    'cwd' => $cwd,
    'name' => "task_1"
)));
$queue->pushTask(ShellRunnable::factory(array(
    'cmd' => "bash $script ../out2 30",
    'cwd' => $cwd,
    'name' => "task_2"
)));
$queue->pushTask(ShellRunnable::factory(array(
    'cmd' => "bash $script ../out3 30",
    'cwd' => $cwd,
    'name' => "task_3"
)));

$runner = Runner::factory(array(
    'tasks' => $queue,
    'wait' => 1000000,
    'daemon' => true,
    'max_processes' => 10
));

//so we can kill me later
file_put_contents('test.pid', getmypid());

$runner->run();
