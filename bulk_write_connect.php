<?php

require 'vendor/autoload.php';

use MongoDB\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'] ?: null; // '<your_mongodb_connection_string_here>'

// CHECK FOR MONGODB_URI
if (!$uri) {
    fwrite(STDERR, "Missing MONGODB_URI. Put it in '.env' next to 'bulk_write_connect.php' or export it in the shell.\n");
    exit(1);
}

// Testing Connect to MongoDB running on MongoDB Atlas
$client = new Client($uri);

// Test connection by listing databases
$databases = $client->listDatabases();
// If the above line does not throw an exception, the connection is successful
echo "Successfully connected to MongoDB!\n";

