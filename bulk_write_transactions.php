<?php

require_once __DIR__ . '/vendor/autoload.php';

use MongoDB\Client;
use MongoDB\ClientBulkWrite;
use MongoDB\Driver\Session;
use Dotenv\Dotenv;

use function MongoDB\with_transaction;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'] ?: null; // '<your_mongodb_connection_string_here>'

// CHECK FOR MONGODB_URI
if (!$uri) {
    fwrite(STDERR, "Missing MONGODB_URI. Put it in '.env' next to 'bulk_write_demo.php' or export it in the shell.\n");
    exit(1);
}

try {
    $client = new Client($uri);

    // Start a session for the transaction
    $session = $client->startSession();

    $customersCollection = $client->selectCollection('shop', 'customers');
    $ordersCollection = $client->selectCollection('shop', 'orders');

    // Use MongoDB\with_transaction() helper for automatic retry handling
    with_transaction($session, function (Session $session) use ($client, $customersCollection, $ordersCollection) {
        // Create bulk write with the session
        $bulkWrite = ClientBulkWrite::createWithCollection($customersCollection, [
            'session' => $session,
            'ordered' => true
        ]);

        // Add operations that must all succeed together
        $bulkWrite->insertOne(['name' => 'Alice', 'email' => 'alice@example.com', 'balance' => 1000]);
        $bulkWrite->updateOne(
            ['name' => 'Bob'],
            ['$inc' => ['balance' => -500]]
        );

        // Switch to orders collection (same transaction)
        $bulkWrite = $bulkWrite->withCollection($ordersCollection);
        $bulkWrite->insertOne([
            'customer' => 'Bob',
            'recipient' => 'Alice',
            'amount' => 500,
            'type' => 'transfer'
        ]);

        // Execute all operations atomically
        $client->bulkWrite($bulkWrite);
    });

    echo "Transaction committed successfully!\n";
} catch (Exception $e) {
    echo "Transaction failed: " . $e->getMessage() . "\n";
    exit(1);
}
