#!/bin/bash

dest=$1
sleepFor=$2

# echo "Started" > "$dest"

function writeToFile {
    echo "$1" > "$dest"
    exit $2
}

trap 'writeToFile "SIGTERM" 7' SIGTERM
trap 'writeToFile "SIGINT" 8' SIGINT

loop=1
while [ $loop = 1 ];
do
    sleep 1
done

