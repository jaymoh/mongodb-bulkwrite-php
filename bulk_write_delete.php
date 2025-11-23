<?php

require 'vendor/autoload.php';

use MongoDB\Client;

$client = new Client("mongodb+srv://jamesshisiah_db_user:<db_password>@bulkwrite.vcdi5mk.mongodb.net/?appName=BulkWrite");

try {
    $database = $client->selectDatabase('bulkwritedb');
    $collection = $database->selectCollection('customers');

    // Prepare bulk delete operations
    $operations = [
        // Delete a specific customer by name
        [
            'deleteOne' => [
                ['name' => 'Alice']              // Filter
            ]
        ],

        // Delete all customers with inactive status
        [
            'deleteMany' => [
                ['status' => 'inactive']         // Filter
            ]
        ],

        // Delete all customers under a certain age
        [
            'deleteMany' => [
                ['age' => ['$lt' => 25]]         // Filter
            ]
        ],
    ];

    $result = $collection->bulkWrite($operations);

    // Output results
    echo "Deleted:   " . $result->getDeletedCount() . "\n";
} catch (\Exception $e) {
    echo "Error connecting to MongoDB: " . $e->getMessage() . "\n";
    exit(1);
}
