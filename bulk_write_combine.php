<?php

require 'vendor/autoload.php';

use MongoDB\Client;

$client = new Client("mongodb+srv://jamesshisiah_db_user:<db_password>@bulkwrite.vcdi5mk.mongodb.net/?appName=BulkWrite");

try {
    $database = $client->selectDatabase('bulkwritedb');
    $collection = $database->selectCollection('users');

    // Prepare combined bulk write operations
    $operations = [
        // Insert new users
        ['insertOne' => [['name' => 'David', 'age' => 35, 'status' => 'active']]],
        ['insertOne' => [['name' => 'Emma', 'age' => 28, 'status' => 'active']]],

        // Update existing users
        [
            'updateOne' => [
                ['name' => 'David'],
                ['$set' => ['role' => 'admin']]
            ]
        ],
        [
            'updateMany' => [
                ['status' => 'active'],
                ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime((int) (microtime(true) * 1000))]]
            ]
        ],

        // Delete users
        [
            'deleteOne' => [
                ['age' => ['$lt' => 20]]
            ]
        ],
        [
            'deleteMany' => [
                ['status' => 'inactive']
            ]
        ],
    ];

    $result = $collection->bulkWrite($operations);

    // Output results
    echo "Inserted:  " . $result->getInsertedCount() . "\n";
    echo "Matched:   " . $result->getMatchedCount() . "\n";
    echo "Modified:  " . $result->getModifiedCount() . "\n";
    echo "Deleted:   " . $result->getDeletedCount() . "\n";
} catch (\Exception $e) {
    echo "Error connecting to MongoDB: " . $e->getMessage() . "\n";
    exit(1);
}
