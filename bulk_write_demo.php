<?php
require 'vendor/autoload.php';

use MongoDB\Client;
use MongoDB\ClientBulkWrite;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'] ?: null; // '<your_mongodb_connection_string_here>'

try {
    // CREATE MONGODB CLIENT - CONNECT TO CLUSTER
    $client = new Client($uri);

    // ===========================================
    // E-COMMERCE APPLICATION COLLECTIONS
    // ===========================================
    $ecommerceDb = $client->ecommerce;
    $customersCollection = $ecommerceDb->customers;
    $ordersCollection = $ecommerceDb->orders;

    // ===========================================
    // RESTAURANT APPLICATION COLLECTIONS
    // ===========================================
    $restaurantDb = $client->restaurant;
    $menusCollection = $restaurantDb->menus;
    $reservationsCollection = $restaurantDb->reservations;

    // ===========================================
    // CREATE CLIENT BULK WRITE INSTANCE
    // Start with the customers collection (e-commerce DB)
    // ===========================================
    $bulkWrite = ClientBulkWrite::createWithCollection($customersCollection);

    // -------------------------------------------
    // insertOne() - Insert customers
    // -------------------------------------------
    $bulkWrite->insertOne(
        ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'status' => 'active'],
        $aliceId
    );
    $bulkWrite->insertOne(
        ['name' => 'Bob Smith', 'email' => 'bob@example.com', 'status' => 'active'],
        $bobId
    );
    $bulkWrite->insertOne(
        ['name' => 'Charlie Brown', 'email' => 'charlie@example.com', 'status' => 'inactive'],
        $charlieId
    );

    // -------------------------------------------
    // updateOne() - Update a single customer
    // -------------------------------------------
    $bulkWrite->updateOne(
        ['email' => 'alice@example.com'],
        ['$set' => ['status' => 'premium', 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
    );

    // -------------------------------------------
    // updateMany() - Update multiple customers
    // -------------------------------------------
    $bulkWrite->updateMany(
        ['status' => 'active'],
        ['$set' => ['newsletter' => true]]
    );

    // -------------------------------------------
    // replaceOne() - Replace entire document
    // -------------------------------------------
    $bulkWrite->replaceOne(
        ['email' => 'charlie@example.com'],
        [
            'name' => 'Charlie Brown',
            'email' => 'charlie.brown@example.com',
            'status' => 'active',
            'migrated' => true
        ]
    );

    // -------------------------------------------
    // deleteOne() - Delete a single customer
    // -------------------------------------------
    $bulkWrite->deleteOne(['email' => 'bob@example.com']);

    // ===========================================
    // SWITCH TO ORDERS COLLECTION (same database - ecommerce)
    // ===========================================
    $bulkWrite = $bulkWrite->withCollection($ordersCollection);

    // insertOne() - Insert orders
    $bulkWrite->insertOne([
        'customer_email' => 'alice@example.com',
        'items' => ['Widget A', 'Widget B'],
        'total' => 99.99,
        'status' => 'pending'
    ]);
    $bulkWrite->insertOne([
        'customer_email' => 'alice@example.com',
        'items' => ['Gadget X'],
        'total' => 149.99,
        'status' => 'pending'
    ]);

    // updateOne() - Update order status
    $bulkWrite->updateOne(
        ['customer_email' => 'alice@example.com', 'status' => 'pending'],
        ['$set' => ['status' => 'processing']]
    );

    // ===========================================
    // SWITCH TO RESTAURANT DATABASE - MENUS
    // ===========================================
    $bulkWrite = $bulkWrite->withCollection($menusCollection);

    // insertOne() - Insert menu items
    $bulkWrite->insertOne([
        'name' => 'Margherita Pizza',
        'price' => 12.99,
        'category' => 'pizza',
        'available' => true
    ]);
    $bulkWrite->insertOne([
        'name' => 'Caesar Salad',
        'price' => 8.99,
        'category' => 'salad',
        'available' => true
    ]);
    $bulkWrite->insertOne([
        'name' => 'Seasonal Special',
        'price' => 15.99,
        'category' => 'special',
        'available' => false
    ]);

    // updateMany() - Update prices for a category
    $bulkWrite->updateMany(
        ['category' => 'pizza'],
        ['$mul' => ['price' => 1.1]] // 10% price increase
    );

    // deleteMany() - Remove unavailable items
    $bulkWrite->deleteMany(['available' => false]);

    // ===========================================
    // SWITCH TO RESTAURANT DATABASE - RESERVATIONS
    // ===========================================
    $bulkWrite = $bulkWrite->withCollection($reservationsCollection);

    // insertOne() - Insert reservations
    $bulkWrite->insertOne([
        'guest_name' => 'John Doe',
        'party_size' => 4,
        'date' => new MongoDB\BSON\UTCDateTime(strtotime('+1 day') * 1000),
        'status' => 'confirmed'
    ]);
    $bulkWrite->insertOne([
        'guest_name' => 'Jane Doe',
        'party_size' => 2,
        'date' => new MongoDB\BSON\UTCDateTime(strtotime('+2 days') * 1000),
        'status' => 'pending'
    ]);

    // replaceOne() - Replace a reservation with upsert
    $bulkWrite->replaceOne(
        ['guest_name' => 'VIP Guest'],
        [
            'guest_name' => 'VIP Guest',
            'party_size' => 8,
            'date' => new MongoDB\BSON\UTCDateTime(strtotime('+3 days') * 1000),
            'status' => 'confirmed',
            'special_requests' => 'Private room'
        ],
        ['upsert' => true]
    );

    // ===========================================
    // EXECUTE ALL OPERATIONS IN A SINGLE REQUEST
    // ===========================================
    echo "Executing bulk write operations across multiple databases and collections...\n\n";

    $result = $client->bulkWrite($bulkWrite);

    // ===========================================
    // DISPLAY RESULTS
    // ===========================================
    echo "=== Bulk Write Results ===\n";
    echo "Inserted Count:  " . $result->getInsertedCount() . "\n";
    echo "Matched Count:   " . $result->getMatchedCount() . "\n";
    echo "Modified Count:  " . $result->getModifiedCount() . "\n";
    echo "Upserted Count:  " . $result->getUpsertedCount() . "\n";
    echo "Deleted Count:   " . $result->getDeletedCount() . "\n";

    echo "\n=== Operations Summary ===\n";
    echo "✓ E-commerce DB (customers): inserted, updated, replaced, deleted\n";
    echo "✓ E-commerce DB (orders): inserted, updated\n";
    echo "✓ Restaurant DB (menus): inserted, updated many, deleted many\n";
    echo "✓ Restaurant DB (reservations): inserted, replaced with upsert\n";

    echo "\nBulk write completed successfully!\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
