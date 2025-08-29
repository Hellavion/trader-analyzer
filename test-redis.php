<?php
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    echo "Redis connection OK\n";
    $redis->ping();
    echo "Redis ping OK\n";
} catch(Exception $e) {
    echo "Redis error: " . $e->getMessage() . "\n";
}