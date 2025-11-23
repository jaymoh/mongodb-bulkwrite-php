<?php
require 'vendor/autoload.php';

use MongoDB\Client;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Driver\WriteConcern;

$uri = 'mongodb+srv://jamesshisiah_db_user:<db_password>@bulkwrite.vcdi5mk.mongodb.net/?appName=BulkWrite';

$client = new Client($uri);
$collection = $client->selectDatabase('bulkwritedb')->selectCollection('users');

$operations = [
    ['insertOne' => [['name' => 'Dana', 'status' => 'active']]],
    ['updateOne' => [['name' => 'Dana'], ['$set' => ['role' => 'admin']]]],
    ['deleteOne' => [['name' => 'NonExisting']]],
];

try {
    $wc = new WriteConcern(WriteConcern::MAJORITY); // use majority for stronger durability

    $result = $collection->bulkWrite($operations, [
        'ordered' => true,
        'writeConcern' => $wc,
    ]);

    echo "Inserted:  " . $result->getInsertedCount() . "\n";
    echo "Matched:   " . $result->getMatchedCount() . "\n";
    echo "Modified:  " . $result->getModifiedCount() . "\n";
    echo "Deleted:   " . $result->getDeletedCount() . "\n";
    echo "Upserts:   " . $result->getUpsertedCount() . "\n";

    foreach ($result->getUpsertedIds() as $i => $id) {
        echo "Upserted[$i] _id: $id" . "\n";
    }
} catch (BulkWriteException $e) {
    $partial = $e->getWriteResult();
    echo "Partial Inserted: " . $partial->getInsertedCount() . "\n";
    echo "Partial Modified: " . $partial->getModifiedCount() . "\n";

    foreach ($partial->getWriteErrors() as $err) {
        echo "Write Error idx=" . $err->getIndex() . " code=" . $err->getCode()
            . " msg=" . $err->getMessage() . "\n";
    }
    if ($wcErr = $partial->getWriteConcernError()) {
        echo "Write Concern Error code=" . $wcErr->getCode()
            . " msg=" . $wcErr->getMessage() . "\n";
    }
} catch (DriverException $e) {
    echo "Driver Error: " . $e->getMessage() . "\n";
}
