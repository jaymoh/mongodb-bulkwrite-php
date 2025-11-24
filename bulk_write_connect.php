<?php

require 'vendor/autoload.php';

use MongoDB\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'] ?: null; // '<your_mongodb_connection_string_here>'

// Connect to MongoDB running on MongoDB Atlas
// Replace your connection string in the braces
// e.g. mongodb+srv://jamesshisiah_db_user:<db_password>@bulkwrite.vcdi5mk.mongodb.net/?appName=BulkWrite
// $client = new Client("your_connection_string");
$client = new Client($uri);

// Select the database and collection
try {
    $database = $client->selectDatabase('bulkwritedb');
    $collection = $database->selectCollection('customers');
} catch (\Exception $e) {
    echo "Error connecting to MongoDB: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Connected to MongoDB successfully.\n";
