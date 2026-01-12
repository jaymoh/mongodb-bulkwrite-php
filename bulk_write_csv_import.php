<?php
/**
 * MongoDB Client BulkWrite - CSV Import Example
 *
 * This script demonstrates using the new Client BulkWrite API to import
 * data from multiple CSV files into different collections in a single
 * bulk operation.
 *
 * Key advantages over the old Collection::bulkWrite() API:
 * - Cross-collection operations in a single batch
 * - Cursor-based results (no 16MB response limit)
 * - Larger batch sizes are practical
 *
 * BATCHING RECOMMENDATION:
 * While the new API can handle very large operations, batching is still
 * recommended for:
 * - Memory efficiency (don't load entire CSV into memory)
 * - Network reliability (smaller retries on failure)
 * - Progress tracking and resumability
 * - Server resource management
 *
 * The key difference: batch sizes can be MUCH larger (e.g., 5000-10000)
 * compared to the old API (typically 500-1000) since we're not limited
 * by the 16MB BSON response document size.
 */

require 'vendor/autoload.php';

use MongoDB\Client;
use MongoDB\ClientBulkWrite;
use MongoDB\BSON\UTCDateTime;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$uri = $_ENV['MONGODB_URI'] ?: null;
$dbName = $_ENV['MONGODB_DB'] ?: 'bulkwritedb';

if (!$uri) {
    fwrite(STDERR, "Missing MONGODB_URI. Put it in '.env' or export it in the shell.\n");
    exit(1);
}

// ===========================================
// CONFIGURATION
// ===========================================
$customersCSV = 'customers_10000.csv';
$organizationsCSV = 'organizations_10000.csv';

// With the new Client BulkWrite API, we can use larger batch sizes
// since results are returned via cursor (no 16MB response limit)
$batchSize = 5000; // Much larger than old API's typical 500-1000

// ===========================================
// HELPER FUNCTIONS
// ===========================================

/**
 * Parse a date string into MongoDB UTCDateTime
 */
function parseDate(?string $raw): ?UTCDateTime
{
    if (!$raw || trim($raw) === '') return null;
    $ts = strtotime($raw);
    return $ts ? new UTCDateTime($ts * 1000) : null;
}

/**
 * Parse a year into MongoDB UTCDateTime (January 1st of that year)
 */
function parseYear(?string $raw): ?UTCDateTime
{
    if (!$raw || trim($raw) === '') return null;
    $year = (int)$raw;
    if ($year < 1800 || $year > 2100) return null;
    return new UTCDateTime(strtotime("$year-01-01") * 1000);
}

/**
 * Parse number of employees to integer
 */
function parseEmployeeCount(?string $raw): ?int
{
    if (!$raw || trim($raw) === '') return null;
    return (int)preg_replace('/[^0-9]/', '', $raw);
}

/**
 * Read CSV file and return header + handle
 */
function openCSV(string $path): array
{
    if (!is_readable($path)) {
        throw new RuntimeException("CSV not readable: $path");
    }

    $handle = fopen($path, 'r');
    if (!$handle) {
        throw new RuntimeException("Failed to open CSV: $path");
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException("Empty CSV: $path");
    }

    // Normalize column names to lowercase
    $colMap = array_map(fn($h) => strtolower(trim($h)), $header);

    return [$handle, $colMap];
}

/**
 * Map customer CSV row to MongoDB document
 */
function mapCustomerRow(array $data): array
{
    return [
        'customer_id' => $data['customer id'] ?? null,
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
        'imported_at' => new UTCDateTime(),
    ];
}

/**
 * Map organization CSV row to MongoDB document
 */
function mapOrganizationRow(array $data): array
{
    return [
        'organization_id' => $data['organization id'] ?? null,
        'name' => $data['name'] ?? null,
        'website' => $data['website'] ?? null,
        'country' => $data['country'] ?? null,
        'description' => $data['description'] ?? null,
        'founded' => parseYear($data['founded'] ?? null),
        'industry' => $data['industry'] ?? null,
        'number_of_employees' => parseEmployeeCount($data['number of employees'] ?? null),
        'imported_at' => new UTCDateTime(),
    ];
}

// ===========================================
// MAIN IMPORT LOGIC
// ===========================================

try {
    $client = new Client($uri);
    $database = $client->selectDatabase($dbName);

    // Get collection references using fluent API
    $customersCollection = $database->selectCollection('customers');
    $organizationsCollection = $database->selectCollection('organizations');

    // Clean up from previous runs
    echo "Preparing collections...\n";
    $customersCollection->drop();
    $organizationsCollection->drop();

    // Create indexes for upsert operations
    $customersCollection->createIndex(['customer_id' => 1], ['unique' => true]);
    $organizationsCollection->createIndex(['organization_id' => 1], ['unique' => true]);

    echo "=== MongoDB Client BulkWrite - Multi-CSV Import ===\n\n";

    // Open CSV files
    [$customersHandle, $customersColMap] = openCSV($customersCSV);
    [$orgsHandle, $orgsColMap] = openCSV($organizationsCSV);

    // Statistics tracking
    $stats = [
        'customers_processed' => 0,
        'organizations_processed' => 0,
        'batches_executed' => 0,
        'inserted' => 0,
        'matched' => 0,
        'modified' => 0,
        'upserted' => 0,
    ];

    $startTime = microtime(true);
    $operationCount = 0;
    $bulkWrite = null;

    /**
     * Execute the current bulk write batch
     */
    $executeBatch = function() use ($client, &$bulkWrite, &$stats, &$operationCount) {
        if (!$bulkWrite || $operationCount === 0) return;

        $result = $client->bulkWrite($bulkWrite);

        $stats['batches_executed']++;
        $stats['inserted'] += $result->getInsertedCount();
        $stats['matched'] += $result->getMatchedCount();
        $stats['modified'] += $result->getModifiedCount();
        $stats['upserted'] += $result->getUpsertedCount();

        $bulkWrite = null;
        $operationCount = 0;
    };

    /**
     * Ensure we have an active bulk write instance
     */
    $ensureBulkWrite = function($collection) use (&$bulkWrite) {
        if ($bulkWrite === null) {
            $bulkWrite = ClientBulkWrite::createWithCollection($collection, [
                'ordered' => false,  // Unordered for better performance
                'verboseResults' => false,  // Disable for large imports (memory efficiency)
            ]);
        }
        return $bulkWrite;
    };

    echo "Importing customers and organizations...\n";
    echo "(Batch size: $batchSize operations)\n\n";

    // ===========================================
    // INTERLEAVED IMPORT - Both CSVs in single batches
    // This demonstrates cross-collection bulk writes
    // ===========================================

    $customersEOF = false;
    $orgsEOF = false;

    while (!$customersEOF || !$orgsEOF) {
        // Read customers
        if (!$customersEOF) {
            $row = fgetcsv($customersHandle);
            if ($row === false) {
                $customersEOF = true;
            } elseif (count($row) === count($customersColMap)) {
                $data = array_combine($customersColMap, $row);
                $doc = mapCustomerRow($data);

                $bw = $ensureBulkWrite($customersCollection);
                // Switch to customers collection and add upsert operation
                $bw = $bw->withCollection($customersCollection);
                $bw->updateOne(
                    ['customer_id' => $doc['customer_id']],
                    ['$set' => $doc],
                    ['upsert' => true]
                );
                $bulkWrite = $bw;

                $operationCount++;
                $stats['customers_processed']++;
            }
        }

        // Read organizations
        if (!$orgsEOF) {
            $row = fgetcsv($orgsHandle);
            if ($row === false) {
                $orgsEOF = true;
            } elseif (count($row) === count($orgsColMap)) {
                $data = array_combine($orgsColMap, $row);
                $doc = mapOrganizationRow($data);

                $bw = $ensureBulkWrite($organizationsCollection);
                // Switch to organizations collection and add upsert operation
                $bw = $bw->withCollection($organizationsCollection);
                $bw->updateOne(
                    ['organization_id' => $doc['organization_id']],
                    ['$set' => $doc],
                    ['upsert' => true]
                );
                $bulkWrite = $bw;

                $operationCount++;
                $stats['organizations_processed']++;
            }
        }

        // Execute batch when threshold reached
        if ($operationCount >= $batchSize) {
            $executeBatch();

            $totalProcessed = $stats['customers_processed'] + $stats['organizations_processed'];
            echo "  Processed: $totalProcessed records (Batch #{$stats['batches_executed']})\n";

            // Re-initialize for next batch
            $bulkWrite = null;
        }
    }

    // Execute remaining operations
    $executeBatch();

    // Close file handles
    fclose($customersHandle);
    fclose($orgsHandle);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);

    // ===========================================
    // RESULTS SUMMARY
    // ===========================================
    echo "\n=== Import Complete ===\n";
    echo "Duration: {$duration}s\n\n";

    echo "Records Processed:\n";
    echo "  Customers:     {$stats['customers_processed']}\n";
    echo "  Organizations: {$stats['organizations_processed']}\n";
    echo "  Total:         " . ($stats['customers_processed'] + $stats['organizations_processed']) . "\n\n";

    echo "Bulk Write Statistics:\n";
    echo "  Batches Executed: {$stats['batches_executed']}\n";
    echo "  Inserted:         {$stats['inserted']}\n";
    echo "  Matched:          {$stats['matched']}\n";
    echo "  Modified:         {$stats['modified']}\n";
    echo "  Upserted:         {$stats['upserted']}\n\n";

    // Verify import
    echo "=== Verification ===\n";
    echo "Customers in DB:     " . $customersCollection->countDocuments() . "\n";
    echo "Organizations in DB: " . $organizationsCollection->countDocuments() . "\n";

    // Sample documents
    echo "\n=== Sample Documents ===\n";
    echo "\nCustomer sample:\n";
    $sample = $customersCollection->findOne();
    if ($sample) {
        echo "  {$sample['first_name']} {$sample['last_name']} - {$sample['email']}\n";
        echo "  Company: {$sample['company']}, Country: {$sample['country']}\n";
    }

    echo "\nOrganization sample:\n";
    $sample = $organizationsCollection->findOne();
    if ($sample) {
        echo "  {$sample['name']} - {$sample['industry']}\n";
        echo "  Employees: {$sample['number_of_employees']}, Country: {$sample['country']}\n";
    }

    echo "\n✓ Multi-collection CSV import completed successfully!\n";

} catch (MongoDB\Driver\Exception\BulkWriteCommandException $e) {
    echo "\n=== Bulk Write Error ===\n";
    echo "Error: " . $e->getMessage() . "\n";

    // Get partial results
    $partialResult = $e->getPartialResult();
    if ($partialResult) {
        echo "\nPartial Results (operations that succeeded):\n";
        echo "  Inserted: " . $partialResult->getInsertedCount() . "\n";
        echo "  Upserted: " . $partialResult->getUpsertedCount() . "\n";
        echo "  Modified: " . $partialResult->getModifiedCount() . "\n";
    }

    exit(1);

} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
