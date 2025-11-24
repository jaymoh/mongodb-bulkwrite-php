<?php

require 'vendor/autoload.php';

use MongoDB\Client;

$client = new Client("your_connection_string");

try {
    $database = $client->selectDatabase('bulkwritedb');
    $collection = $database->selectCollection('customers');

    $operations = [
        ['insertOne' => [ ['name' => 'Alice', 'age' => 25] ]],
        ['insertOne' => [ ['name' => 'Bob', 'age' => 30] ]],
        ['insertOne' => [ ['name' => 'Charlie', 'age' => 28] ]],
    ];

    $result = $collection->bulkWrite($operations);
    echo "Inserted documents: " . $result->getInsertedCount() . "\n";
} catch (\Exception $e) {
    echo "Error connecting to MongoDB: " . $e->getMessage() . "\n";
    exit(1);
}
