<?php

require 'vendor/autoload.php';

use MongoDB\Client;

// Connect to MongoDB running on MongoDB Atlas
// Replace your connection string in the braces
// $client = new Client("your_connection_string");
$client = new Client("mongodb+srv://jamesshisiah_db_user:<db_password>@bulkwrite.vcdi5mk.mongodb.net/?appName=BulkWrite");

// Select the database and collection
try {
    $database = $client->selectDatabase('bulkwritedb');
    $collection = $database->selectCollection('customers');
} catch (\Exception $e) {
    echo "Error connecting to MongoDB: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Connected to MongoDB successfully.\n";
