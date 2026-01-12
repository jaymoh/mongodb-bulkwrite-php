<?php
require 'vendor/autoload.php';

use MongoDB\Client;
use MongoDB\ClientBulkWrite;
use Dotenv\Dotenv;
use MongoDB\Driver\WriteConcern;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'] ?: null; // '<your_mongodb_connection_string_here>'

// CHECK FOR MONGODB_URI
if (!$uri) {
    fwrite(STDERR, "Missing MONGODB_URI. Put it in '.env' next to 'bulk_write_connect.php' or export it in the shell.\n");
    exit(1);
}

$writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000); // majority acknowledgement, 1 second timeout

try {
    $client = new Client($uri);

    // ===========================================
    // SETUP: Create a collection for demonstration
    // ===========================================
    $database = $client->tutorial;
    $collection = $database->bulk_results_demo;

    // Clean up from previous runs
    $collection->drop();

    echo "=== MongoDB Client BulkWrite - Handling Results ===\n\n";

    // ===========================================
    // VERBOSE RESULTS: The New Cursor-Based API
    // ===========================================
    // The new Client BulkWrite API can return detailed results via cursor.
    // This is a significant improvement over the old API, which could fail
    // if the response document exceeded the 16MB BSON document size limit.
    //
    // With verboseResults enabled, you get per-operation details:
    // - getInsertResults(): Details for each insert operation
    // - getUpdateResults(): Details for each update/replace operation
    // - getDeleteResults(): Details for each delete operation
    // ===========================================

    echo "Creating bulk write with verboseResults enabled and custom WriteConcern...\n\n";

    // Create ClientBulkWrite with verboseResults option
    $bulkWrite = ClientBulkWrite::createWithCollection($collection, [
        'ordered' => true,
        'verboseResults' => true,  // Enable detailed per-operation results
        'writeConcern' => $writeConcern,
    ]);

    // -------------------------------------------
    // Add multiple insert operations
    // -------------------------------------------
    $bulkWrite->insertOne(['name' => 'Product A', 'price' => 29.99, 'category' => 'electronics'], $id1);
    $bulkWrite->insertOne(['name' => 'Product B', 'price' => 49.99, 'category' => 'electronics'], $id2);
    $bulkWrite->insertOne(['name' => 'Product C', 'price' => 19.99, 'category' => 'books'], $id3);
    $bulkWrite->insertOne(['name' => 'Product D', 'price' => 99.99, 'category' => 'electronics'], $id4);
    $bulkWrite->insertOne(['name' => 'Product E', 'price' => 9.99, 'category' => 'books'], $id5);

    // -------------------------------------------
    // Add update operations
    // -------------------------------------------
    // updateOne - Update a single product's price
    $bulkWrite->updateOne(
        ['name' => 'Product A'],
        ['$set' => ['price' => 24.99, 'on_sale' => true]]
    );

    // updateMany - Update all electronics
    $bulkWrite->updateMany(
        ['category' => 'electronics'],
        ['$set' => ['in_stock' => true]]
    );

    // updateOne with upsert - Will insert if not found
    $bulkWrite->updateOne(
        ['name' => 'Product F'],
        ['$set' => ['name' => 'Product F', 'price' => 39.99, 'category' => 'clothing']],
        ['upsert' => true]
    );

    // replaceOne - Replace entire document
    $bulkWrite->replaceOne(
        ['name' => 'Product E'],
        ['name' => 'Product E', 'price' => 14.99, 'category' => 'books', 'bestseller' => true]
    );

    // -------------------------------------------
    // Add delete operations
    // -------------------------------------------
    // deleteOne - Delete a single product
    $bulkWrite->deleteOne(['name' => 'Product C']);

    // deleteMany - Delete by category (will delete remaining books)
    $bulkWrite->deleteMany(['category' => 'books']);

    // ===========================================
    // EXECUTE BULK WRITE
    // ===========================================
    echo "Executing bulk write...\n\n";
    $result = $client->bulkWrite($bulkWrite);

    // ===========================================
    // BASIC RESULT COUNTS
    // ===========================================
    echo "=== Summary Counts ===\n";
    echo "Inserted:  " . $result->getInsertedCount() . "\n";
    echo "Matched:   " . $result->getMatchedCount() . "\n";
    echo "Modified:  " . $result->getModifiedCount() . "\n";
    echo "Upserted:  " . $result->getUpsertedCount() . "\n";
    echo "Deleted:   " . $result->getDeletedCount() . "\n";
    echo "Acknowledged: " . ($result->isAcknowledged() ? 'Yes' : 'No') . "\n";

    // ===========================================
    // VERBOSE RESULTS - Per-Operation Details
    // These methods return cursor-iteratable results that can handle
    // responses larger than 16MB BSON limit (unlike old API)
    // ===========================================

    echo "\n=== Verbose Insert Results ===\n";
    echo "(Per-operation details returned via cursor)\n\n";

    $insertResults = $result->getInsertResults();
    if ($insertResults) {
        foreach ($insertResults as $index => $insertResult) {
            echo "Insert operation #$index:\n";
            echo "  Inserted ID: " . $insertResult->insertedId . "\n";
        }
    } else {
        echo "No verbose insert results (verboseResults may be disabled)\n";
    }

    echo "\n=== Verbose Update Results ===\n";
    echo "(Includes updateOne, updateMany, replaceOne operations)\n\n";

    $updateResults = $result->getUpdateResults();
    if ($updateResults) {
        foreach ($updateResults as $index => $updateResult) {
            echo "Update operation #$index:\n";
            echo "  Matched:  " . $updateResult->matchedCount . "\n";
            echo "  Modified: " . $updateResult->modifiedCount . "\n";
            if (isset($updateResult->upsertedId)) {
                echo "  Upserted ID: " . $updateResult->upsertedId . "\n";
            }
        }
    } else {
        echo "No verbose update results (verboseResults may be disabled)\n";
    }

    echo "\n=== Verbose Delete Results ===\n";
    echo "(Includes deleteOne, deleteMany operations)\n\n";

    $deleteResults = $result->getDeleteResults();
    if ($deleteResults) {
        foreach ($deleteResults as $index => $deleteResult) {
            echo "Delete operation #$index:\n";
            echo "  Deleted: " . $deleteResult->deletedCount . "\n";
        }
    } else {
        echo "No verbose delete results (verboseResults may be disabled)\n";
    }

    // ===========================================
    // WHY CURSOR-BASED RESULTS MATTER
    // ===========================================
    echo "\n=== Why Cursor-Based Results Matter ===\n";
    echo "
The OLD Collection::bulkWrite() API returned results in a single BSON document.
This had a critical limitation: if you performed thousands of operations,
the response document could exceed MongoDB's 16MB BSON document size limit,
causing the entire operation to fail.

The NEW Client::bulkWrite() API returns verbose results via a CURSOR.
This means:
✓ Results are streamed incrementally, not loaded all at once
✓ No 16MB response size limit - handle millions of operations
✓ Memory efficient for large bulk writes  
✓ Per-operation details available for auditing and debugging
✓ Cross-database/collection operations in a single atomic batch

Example: A bulk write with 100,000 insert operations would fail with the
old API if returning all inserted IDs exceeded 16MB. With the new API,
these results stream via cursor with no size restrictions.
";

    // ===========================================
    // DISPLAY FINAL COLLECTION STATE
    // ===========================================
    echo "\n=== Final Collection Contents ===\n";
    $documents = $collection->find([], ['sort' => ['name' => 1]]);
    foreach ($documents as $doc) {
        echo "- {$doc['name']}: \${$doc['price']} ({$doc['category']})";
        if (isset($doc['on_sale']) && $doc['on_sale']) echo " [ON SALE]";
        if (isset($doc['in_stock']) && $doc['in_stock']) echo " [IN STOCK]";
        if (isset($doc['bestseller']) && $doc['bestseller']) echo " [BESTSELLER]";
        echo "\n";
    }

    echo "\nBulk write results demonstration completed!\n";

} catch (MongoDB\Driver\Exception\BulkWriteCommandException $e) {
    // ===========================================
    // HANDLING BULK WRITE ERRORS
    // ===========================================
    echo "\n=== Bulk Write Error ===\n";
    echo "Error Message: " . $e->getMessage() . "\n";

    // Get partial results (operations that succeeded before the error)
    $partialResult = $e->getPartialResult();
    if ($partialResult) {
        echo "\nPartial Results (before failure):\n";
        echo "  Inserted: " . $partialResult->getInsertedCount() . "\n";
        echo "  Modified: " . $partialResult->getModifiedCount() . "\n";
        echo "  Deleted: " . $partialResult->getDeletedCount() . "\n";
    }

    // Get write errors for specific operations that failed
    $writeErrors = $e->getWriteErrors();
    if ($writeErrors) {
        echo "\nWrite Errors:\n";
        foreach ($writeErrors as $index => $error) {
            echo "  Operation #$index: " . $error->getMessage() . "\n";
        }
    }

    // Get write concern errors if any
    $writeConcernErrors = $e->getWriteConcernErrors();
    if ($writeConcernErrors) {
        echo "\nWrite Concern Errors:\n";
        foreach ($writeConcernErrors as $index => $wcError) {
            echo "  Error #$index: " . $wcError->getMessage() . "\n";
        }
    }

    exit(1);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
