<?php

require 'vendor/autoload.php';

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\BSON\UTCDateTime;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'] ?: null; // '<your_mongodb_connection_string_here>'
$dbName = $_ENV['MONGODB_DB'] ?: 'bulkwritedb';

if (!$uri) {
    fwrite(STDERR, "Missing MONGODB_URI. Put it in '.env' next to 'bulk_write_csv_import.php' or export it in the shell.\n");
    exit(1);
}

$csvPath = 'customers_10000.csv'; // Path to CSV file
$batchSize = 500; // Number of operations per batch

if (!is_readable($csvPath)) {
    fwrite(STDERR, "CSV not readable: $csvPath\n");
    exit(1);
}

$client = new Client($uri);
$collection = $client->selectDatabase($dbName)->selectCollection('customers');
// If necessary, drop existing collection for clean import
$collection->drop();

function parseDate(?string $raw): ?UTCDateTime
{
    if (!$raw) return null;
    $ts = strtotime($raw);
    return $ts ? new UTCDateTime($ts * 1000) : null;
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    fwrite(STDERR, "Failed to open CSV.\n");
    exit(1);
}

$header = fgetcsv($handle);
if (!$header) {
    fwrite(STDERR, "Empty CSV.\n");
    exit(1);
}

$colMap = array_map(fn($h) => strtolower(trim($h)), $header);

$totalRows = 0;
$batchOps = [];
$stats = [
    'inserted' => 0,
    'matched' => 0,
    'modified' => 0,
    'upserted' => 0,
    'deleted' => 0
];

/**
 * A callable to flush the current batch of operations.
 *
 * @param array $ops - Reference to the operations array
 * @param Collection $collection
 * @param array $stats
 * @return void
 */
function flushBatch(array &$ops, Collection $collection, array &$stats): void
{
    if (!$ops) return;
    try {
        $result = $collection->bulkWrite($ops, ['ordered' => false]);
        $stats['inserted'] += $result->getInsertedCount();
        $stats['matched'] += $result->getMatchedCount();
        $stats['modified'] += $result->getModifiedCount();
        $stats['upserted'] += $result->getUpsertedCount();
        $stats['deleted'] += $result->getDeletedCount();
    } catch (Throwable $e) {
        fwrite(STDERR, "Batch error: {$e->getMessage()}\n");
        // Errors are shown on the terminal; continue processing
    }
    $ops = [];
}

while (($row = fgetcsv($handle)) !== false) {
    if (count($row) !== count($colMap)) continue; // skip malformed
    $data = array_combine($colMap, $row);

    $doc = [
        'customer_id' => $data['customer id'],
        'first_name' => $data['first name'] ?? null,
        'last_name' => $data['last name'] ?? null,
        'company' => $data['company'] ?? null,
        'city' => $data['city'] ?? null,
        'country' => $data['country'] ?? null,
        'phone_1' => $data['phone 1'] ?? null,
        'phone_2' => $data['phone 2'] ?? null,
        'email' => $data['email'] ?? null,
        'subscription_date' => parseDate($data['subscription date'] ?? null),
        'website' => $data['website'] ?? null,
        'imported_at' => new UTCDateTime()
    ];

    // Upsert by unique customer_id
    $batchOps[] = [
        'updateOne' => [
            ['customer_id' => $doc['customer_id']],
            ['$set' => $doc],
            ['upsert' => true]
        ]
    ];

    $totalRows++;

    if (count($batchOps) >= $batchSize) {
        flushBatch($batchOps, $collection, $stats);
        if ($totalRows % 1000 === 0) {
            echo "Processed: $totalRows\n";
        }
    }
}

// Flush remaining operations
flushBatch($batchOps, $collection, $stats);
fclose($handle);

echo "Rows processed: $totalRows\n";
echo "Inserted: {$stats['inserted']}\n";
echo "Matched: {$stats['matched']}\n";
echo "Modified: {$stats['modified']}\n";
echo "Upserted: {$stats['upserted']}\n";
echo "Deleted: {$stats['deleted']}\n";
