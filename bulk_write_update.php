<?php

require 'vendor/autoload.php';

use MongoDB\Client;

$client = new Client("mongodb+srv://jamesshisiah_db_user:<db_password>@bulkwrite.vcdi5mk.mongodb.net/?appName=BulkWrite");

try {
    $database = $client->selectDatabase('bulkwritedb');
    $collection = $database->selectCollection('customers');

    // Prepare bulk update operations
    $operations = [
        // Update Alice's age
        [
            'updateOne' => [
                ['name' => 'Alice'],              // Filter
                ['$set' => ['age' => 26]]         // Update
            ]
        ],

        // Add a new field to Bob
        [
            'updateOne' => [
                ['name' => 'Bob'],                // Filter
                ['$set' => ['status' => 'active']]
            ]
        ],

        // Increase Charlie's age by 1
        [
            'updateOne' => [
                ['name' => 'Charlie'],
                ['$inc' => ['age' => 1]]
            ]
        ],

        // Update all customers over 30 to have a "senior" status
        [
            'updateMany' => [
                ['age' => ['$gt' => 30]],           // Filter
                ['$set' => ['category' => 'senior']]  // Update
            ]
        ],

    ];

    $result = $collection->bulkWrite($operations);

    // Output results
    echo "Matched:   " . $result->getMatchedCount() . "\n";
    echo "Modified:  " . $result->getModifiedCount() . "\n";
} catch (\Exception $e) {
    echo "Error connecting to MongoDB: " . $e->getMessage() . "\n";
    exit(1);
}
