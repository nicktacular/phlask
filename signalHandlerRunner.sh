#!/bin/bash

cd tests
php signalHandlerTest.php &

# wait for it to start
sleep 1

if [ ! -f test.pid ]; then
    echo "No pid file was created"
    exit 3
fi

PID=$(cat test.pid)
kill -TERM $PID

#wait a sec
sleep 1

function verify {
    if [ -f "$1" ]; then
        contents=$(cat $1)
        if [ "$contents" == "SIGTERM" ]; then
            echo "Proc $2 passed"
        else
            echo "Proc $2 failed"
            exit 2
        fi
    else
        echo "Proc $2 failed; file was not written!"
        exit 1
    fi
}

verify out1 1
verify out2 2
verify out3 3

rm -f out*
rm test.pid
