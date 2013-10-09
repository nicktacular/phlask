<?php

//start with a root
$root = '/';

function p($msg, $indentLevel = 0)
{return;
    while ($indentLevel) {
        echo ' ';
        $indentLevel--;
    }
    echo $msg . PHP_EOL;
}

function friendlySize($bytes, $dec = 2)
{
    $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    foreach ($sizes as $size) {
        if ($bytes < 1024) {
            return number_format($bytes, $dec) . ' ' . $size;
        }
        
        $bytes /= 1024;
    }

    return ($bytes*1024) . ' ' . $sizes[count($sizes)-1];
}

function drill($path, $showHidden = false, $currDepth = 0)
{
    $size = 0;

    if (!is_readable($path)) {
        p("Could not read $path. Skipping.", $currDepth);
        return false;
    }

    foreach (scandir($path) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        $full = $path . '/' . $item;

        if (is_file($full)) {
            p("f[$item]", $currDepth);
            //get the file size
            $size += filesize($full);
        } elseif (is_dir($full)) {
            p("d[$item]", $currDepth);
            $size += drill($full, $showHidden, $currDepth+1);
        } elseif (is_link($full)) {
            p("l[$item]", $currDepth);
        } else {
            p("?[$item]", $currDepth);
        }
    }

    return $size;
}
$start = microtime(true);
$size = drill('/');
$delta = microtime(true) - $start;
print("Total file size: " . friendlySize($size, 2));
print("Total time: " . number_format($delta, 2) . ' seconds');