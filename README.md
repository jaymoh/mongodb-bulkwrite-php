# Getting Started: MongoDB Bulk Write in PHP

## Overview
Demonstrates grouped write operations (insert, update, delete) using MongoDB PHP driver `bulkWrite()` for efficiency and ordered execution.

## Prerequisites
- PHP 8+
- PHP MongoDB extension installed
- Composer
- MongoDB Atlas cluster or local MongoDB
- Installed driver: `composer require mongodb/mongodb`

## Setup
1. Install dependencies:
   php composer.phar require mongodb/mongodb
2. Create `.env` (recommended for real world apps) and store connection URI (avoid hardcoding credentials).
3. Each example script connects, builds an operations array, then calls:
   $result = $collection->bulkWrite($operations);

## Files
- `bulk_write_insert.php` inserts multiple documents.
- `bulk_write_update.php` shows `updateOne`, `updateMany`, `$set`, `$inc`.
- `bulk_write_delete.php` shows `deleteOne`, `deleteMany`.
- `bulk_write_combine.php` mixes insert, update, delete on `users` collection.
- `bulk_write_errors.php` demonstrates error handling in bulk writes.
- `bulk_write_csv_import.php` demonstrates importing data from CSV to MongoDB using bulk writes.

### Running the bulk_write_csv_import
We have included a sample CSV file `customers_10000.csv` with 10,000 customer records for testing the import script.
To run the CSV import example, first copy `.env.example` to `.env` and set your MongoDB connection string.
Then execute:
```bash
$ php bulk_write_csv_import.php
```

## Ordered vs Unordered
Ordered (default): operations run sequentially; later writes can depend on earlier inserts.
Unordered:
$result = $collection->bulkWrite($operations, ['ordered' => false]);
Can improve throughput; may continue after individual errors.

## Key Operations
- insertOne: add a single document.
- updateOne: modify first matching document.
- updateMany: modify all matching documents.
- deleteOne: remove first matching document.
- deleteMany: remove all matching documents.
- $set: assign or replace field values.
- $inc: increment numeric fields.

## Running Examples
```bash
$ php bulk_write_insert.php
```

```bash
$ php bulk_write_update.php
```

```bash
$ php bulk_write_delete.php
```

```bash
$ php bulk_write_all.php
```

```bash
$ php bulk_write_errors.php
```

```bash
$ php bulk_write_csv_import.php
```

## Result Metrics
- getInsertedCount()
- getMatchedCount()
- getModifiedCount()
- getDeletedCount()
- getUpsertedCount()
- getUpsertedIds()
- getWriteErrors()
- getWriteConcernError()

## Tips
Use environment variables for credentials (recommended).
Validate filters to avoid unintended mass updates/deletes.
Batch or Group related writes to reduce round trips.

## Next Steps
Explore MongoDB docs: [Bulk Write](https://www.mongodb.com/docs/php-library/current/crud/bulk-write/) | [Error Handling](https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBCollection-bulkWrite/#errors-exceptions).
