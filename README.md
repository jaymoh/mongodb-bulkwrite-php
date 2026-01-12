# Getting Started with MongoDB BulkWrite in PHP

This tutorial demonstrates how to use the **new MongoDB Client BulkWrite API** introduced in MongoDB PHP Library 2.x. The new API offers significant improvements over the legacy `Collection::bulkWrite()` method, including cross-database operations and cursor-based results.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Connecting to MongoDB](#connecting-to-mongodb)
- [Understanding Client BulkWrite](#understanding-client-bulkwrite)
- [BulkWrite Operations](#bulkwrite-operations)
  - [insertOne()](#insertone)
  - [updateOne()](#updateone)
  - [updateMany()](#updatemany)
  - [replaceOne()](#replaceone)
  - [deleteOne()](#deleteone)
  - [deleteMany()](#deletemany)
- [Switching Collections and Databases](#switching-collections-and-databases)
- [Handling BulkWrite Results](#handling-bulkwrite-results)
- [Real-World Example: CSV Import](#real-world-example-csv-import)
- [Error Handling](#error-handling)
- [Best Practices](#best-practices)

---

## Prerequisites

- PHP 8.1 or higher
- [MongoDB PHP Extension](https://www.mongodb.com/docs/php-library/current/get-started/) (`mongodb`) - you can install via [PIE](https://github.com/php/pie) 
- [MongoDB PHP Library](https://github.com/mongodb/mongo-php-library) 2.x (`mongodb/mongodb`)
- MongoDB Server 8.0+ (for the new Client BulkWrite support)
- Composer

## Installation

1. Install the MongoDB PHP extension via PIE:

```bash
pie install mongodb/mongodb-extension
```

2. Install the MongoDB PHP Library via Composer:

```bash
composer require mongodb/mongodb:^2.1
```

3. Create a `.env` file with your MongoDB connection string:

```env
MONGODB_URI=mongodb+srv://username:password@cluster.mongodb.net/
MONGODB_DB=bulkwritedb
```

---

## Connecting to MongoDB

Before performing bulk write operations, verify your connection to MongoDB:

```php
<?php
require 'vendor/autoload.php';

use MongoDB\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'];

$client = new Client($uri);

// Test connection
$databases = $client->listDatabases();
echo "Successfully connected to MongoDB!\n";
```

Run the connection test:

```bash
php bulk_write_connect.php
```

---

## Understanding Client BulkWrite

The new `Client::bulkWrite()` API allows you to combine multiple write operations into a single batch request that can be executed across **multiple collections** and **multiple databases** in the same cluster.

### Key Advantages Over Legacy API

| Feature | Legacy `Collection::bulkWrite()` | New `Client::bulkWrite()` |
|---------|----------------------------------|---------------------------|
| Scope | Single collection only | Multiple collections/databases |
| Results | Single BSON document (16MB limit) | Cursor-based (no size limit) |
| Max Operations | Limited by response size | Virtually unlimited |
| Batch Size | Typically 500-1,000 | 5,000-10,000+ |

### Creating a ClientBulkWrite Instance

```php
use MongoDB\ClientBulkWrite;

// Create bulk write starting with a collection
$bulkWrite = ClientBulkWrite::createWithCollection($collection, [
    'ordered' => true,        // Execute operations in order (default: true)
    'verboseResults' => true, // Get per-operation details
]);
```

---

## BulkWrite Operations

### insertOne()

Insert a single document into the collection:

```php
// Insert with captured ID
$bulkWrite->insertOne(
    ['name' => 'Alice Johnson', 'email' => 'alice@example.com', 'status' => 'active'],
    $insertedId  // Optional: captures the generated _id
);
```

### updateOne()

Update a single document matching the filter:

```php
$bulkWrite->updateOne(
    ['email' => 'alice@example.com'],                    // Filter
    ['$set' => ['status' => 'premium', 'updated_at' => new MongoDB\BSON\UTCDateTime()]]  // Update
);

// With upsert option
$bulkWrite->updateOne(
    ['email' => 'new@example.com'],
    ['$set' => ['name' => 'New User', 'email' => 'new@example.com']],
    ['upsert' => true]
);
```

### updateMany()

Update all documents matching the filter:

```php
$bulkWrite->updateMany(
    ['status' => 'active'],           // Filter
    ['$set' => ['newsletter' => true]] // Update
);
```

### replaceOne()

Replace an entire document:

```php
$bulkWrite->replaceOne(
    ['email' => 'old@example.com'],   // Filter
    [                                  // Replacement document
        'name' => 'Updated Name',
        'email' => 'new@example.com',
        'migrated' => true
    ]
);

// With upsert
$bulkWrite->replaceOne(
    ['guest_name' => 'VIP Guest'],
    ['guest_name' => 'VIP Guest', 'party_size' => 8, 'status' => 'confirmed'],
    ['upsert' => true]
);
```

### deleteOne()

Delete a single document:

```php
$bulkWrite->deleteOne(['email' => 'delete@example.com']);
```

### deleteMany()

Delete all documents matching the filter:

```php
$bulkWrite->deleteMany(['status' => 'inactive']);
```

---

## Switching Collections and Databases

One of the most powerful features of the new API is the ability to perform operations across multiple collections and databases in a single batch.

```php
// Start with e-commerce database
$ecommerceDb = $client->ecommerce;
$customersCollection = $ecommerceDb->customers;
$ordersCollection = $ecommerceDb->orders;

// Restaurant database
$restaurantDb = $client->restaurant;
$menusCollection = $restaurantDb->menus;

// Create bulk write starting with customers
$bulkWrite = ClientBulkWrite::createWithCollection($customersCollection);

// Add customer operations
$bulkWrite->insertOne(['name' => 'Alice', 'email' => 'alice@example.com']);
$bulkWrite->updateOne(['name' => 'Alice'], ['$set' => ['status' => 'premium']]);

// Switch to orders collection (same database)
$bulkWrite = $bulkWrite->withCollection($ordersCollection);
$bulkWrite->insertOne(['customer' => 'Alice', 'total' => 99.99]);

// Switch to menus collection (different database!)
$bulkWrite = $bulkWrite->withCollection($menusCollection);
$bulkWrite->insertOne(['name' => 'Pizza', 'price' => 12.99]);
$bulkWrite->deleteMany(['available' => false]);

// Execute ALL operations in a single request
$result = $client->bulkWrite($bulkWrite);
```

---

## Handling BulkWrite Results

### Summary Counts

```php
$result = $client->bulkWrite($bulkWrite);

echo "Inserted:  " . $result->getInsertedCount() . "\n";
echo "Matched:   " . $result->getMatchedCount() . "\n";
echo "Modified:  " . $result->getModifiedCount() . "\n";
echo "Upserted:  " . $result->getUpsertedCount() . "\n";
echo "Deleted:   " . $result->getDeletedCount() . "\n";
echo "Acknowledged: " . ($result->isAcknowledged() ? 'Yes' : 'No') . "\n";
```

### Verbose Results (Cursor-Based)

Enable `verboseResults` to get per-operation details returned via cursor:

```php
$bulkWrite = ClientBulkWrite::createWithCollection($collection, [
    'verboseResults' => true
]);

// ... add operations ...

$result = $client->bulkWrite($bulkWrite);

// Insert results - streamed via cursor
foreach ($result->getInsertResults() as $index => $insertResult) {
    echo "Insert #$index - ID: " . $insertResult->insertedId . "\n";
}

// Update results
foreach ($result->getUpdateResults() as $index => $updateResult) {
    echo "Update #$index - Matched: " . $updateResult->matchedCount . "\n";
    echo "             Modified: " . $updateResult->modifiedCount . "\n";
    if (isset($updateResult->upsertedId)) {
        echo "             Upserted ID: " . $updateResult->upsertedId . "\n";
    }
}

// Delete results
foreach ($result->getDeleteResults() as $index => $deleteResult) {
    echo "Delete #$index - Deleted: " . $deleteResult->deletedCount . "\n";
}
```

### Why Cursor-Based Results Matter

The legacy `Collection::bulkWrite()` API returned results in a single BSON document. If you performed thousands of operations, the response could exceed MongoDB's **16MB BSON document size limit**, causing the operation to fail.

The new `Client::bulkWrite()` API returns verbose results via a **cursor**, which means:

- ✓ Results are streamed incrementally
- ✓ No 16MB response size limit
- ✓ Memory efficient for large bulk writes
- ✓ Handle millions of operations without failure

---

## Real-World Example: CSV Import

Import data from multiple CSV files into different collections:

```php
// Configuration
$batchSize = 5000; // Much larger than legacy API's 500-1000 // You can still go higher/lower based on memory/network

// Open CSV files
$customersHandle = fopen('customers.csv', 'r');
$orgsHandle = fopen('organizations.csv', 'r');

// Skip headers
fgetcsv($customersHandle);
fgetcsv($orgsHandle);

$bulkWrite = null;
$operationCount = 0;

while (!feof($customersHandle) || !feof($orgsHandle)) {
    // Read and add customer
    if (!feof($customersHandle)) {
        $row = fgetcsv($customersHandle);
        if ($row) {
            $bulkWrite = $bulkWrite ?? ClientBulkWrite::createWithCollection($customersCollection, [
                'ordered' => false,
                'verboseResults' => false
            ]);
            $bulkWrite = $bulkWrite->withCollection($customersCollection);
            $bulkWrite->updateOne(
                ['customer_id' => $row[0]],
                ['$set' => ['name' => $row[1], 'email' => $row[2], 'imported_at' => new UTCDateTime()]],
                ['upsert' => true]
            );
            $operationCount++;
        }
    }

    // Read and add organization
    if (!feof($orgsHandle)) {
        $row = fgetcsv($orgsHandle);
        if ($row) {
            $bulkWrite = $bulkWrite->withCollection($organizationsCollection);
            $bulkWrite->updateOne(
                ['org_id' => $row[0]],
                ['$set' => ['name' => $row[1], 'industry' => $row[2], 'imported_at' => new UTCDateTime()]],
                ['upsert' => true]
            );
            $operationCount++;
        }
    }

    // Execute batch when threshold reached
    if ($operationCount >= $batchSize) {
        $client->bulkWrite($bulkWrite);
        $bulkWrite = null;
        $operationCount = 0;
    }
}

// Execute remaining operations
if ($bulkWrite && $operationCount > 0) {
    $client->bulkWrite($bulkWrite);
}
```

---

## Error Handling

```php
try {
    $result = $client->bulkWrite($bulkWrite);
    
} catch (MongoDB\Driver\Exception\BulkWriteCommandException $e) {
    echo "Bulk Write Error: " . $e->getMessage() . "\n";
    
    // Get partial results (operations that succeeded)
    $partialResult = $e->getPartialResult();
    if ($partialResult) {
        echo "Partial Results:\n";
        echo "  Inserted: " . $partialResult->getInsertedCount() . "\n";
        echo "  Upserted: " . $partialResult->getUpsertedCount() . "\n";
        echo "  Modified: " . $partialResult->getModifiedCount() . "\n";
    }
    
    // Get specific write errors
    $writeErrors = $e->getWriteErrors();
    foreach ($writeErrors as $index => $error) {
        echo "Operation #$index failed: " . $error->getMessage() . "\n";
    }
    
    // Get write concern errors
    $writeConcernErrors = $e->getWriteConcernErrors();
    foreach ($writeConcernErrors as $wcError) {
        echo "Write Concern Error: " . $wcError->getMessage() . "\n";
    }
}
```

---

## Best Practices

### 1. Batching Strategy

While the new API can handle very large operations, batching is still recommended:

```php
$batchSize = 5000; // 5-10x larger than legacy API
```

**Why batch?**
- Memory efficiency (don't load entire dataset as operations)
- Network reliability (smaller retries on failure)
- Progress tracking and resumability
- Server resource management

### 2. Ordered vs Unordered

```php
// Ordered (default) - stops on first error
$bulkWrite = ClientBulkWrite::createWithCollection($collection, [
    'ordered' => true
]);

// Unordered - continues after errors, better performance
$bulkWrite = ClientBulkWrite::createWithCollection($collection, [
    'ordered' => false
]);
```

### 3. Verbose Results

```php
// Enable for debugging/auditing
$bulkWrite = ClientBulkWrite::createWithCollection($collection, [
    'verboseResults' => true
]);

// Disable for large imports (memory efficiency)
$bulkWrite = ClientBulkWrite::createWithCollection($collection, [
    'verboseResults' => false
]);
```

### 4. Write Concern

```php
use MongoDB\Driver\WriteConcern;

$writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000);

$bulkWrite = ClientBulkWrite::createWithCollection($collection, [
    'writeConcern' => $writeConcern
]);
```

---

## Tutorial Files

| File | Description |
|------|-------------|
| `bulk_write_connect.php` | Connection test script |
| `bulk_write_demo.php` | Complete demo of all operations across multiple databases |
| `bulk_write_results.php` | Handling verbose results and cursor-based responses |
| `bulk_write_csv_import.php` | Real-world CSV import example |

---

## Running the Examples

```bash
# Test connection
php bulk_write_connect.php

# Run operations demo
php bulk_write_demo.php

# Explore results handling
php bulk_write_results.php

# Import CSV data
php bulk_write_csv_import.php
```

---

## Next Steps
Explore the following resources to deepen your understanding of MongoDB and PHP:

- [MongoDB PHP Library Documentation](https://www.mongodb.com/docs/php-library/current/)
- [Client Bulk Write Guide](https://www.mongodb.com/docs/php-library/current/crud/bulk-write/)
- [MongoDB PHP Extension](https://www.php.net/manual/en/set.mongodb.php)
- [Error Handling in MongoDB PHP Library](https://www.php.net/manual/en/class.mongodb-driver-exception-bulkwritecommandexception.php)

---

## License

MIT License - Feel free to use this tutorial code in your projects.

