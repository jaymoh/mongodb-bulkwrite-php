# Getting Started: MongoDB Bulk Write in PHP

## Overview

Demonstrates grouped write operations (insert, update, delete) using MongoDB PHP driver `bulkWrite()` for efficiency and
ordered execution.

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
php bulk_write_connect.php
```

```bash
 php bulk_write_insert.php
```

```bash
 php bulk_write_update.php
```

```bash
 php bulk_write_delete.php
```

```bash
 php bulk_write_all.php
```

```bash
 php bulk_write_errors.php
```

```bash
 php bulk_write_csv_import.php
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

## Using Docker

If you prefer using Docker, we have included a `Dockerfile` that sets up the PHP environment with the MongoDB extension
and necessary dependencies.
We also provide a `docker-compose.yml` that sets up a MongoDB Atlas container alongside the PHP environment.

Update the `MONGO_URI` in the `docker-compose.yml` to point to your MongoDB Atlas cluster or local MongoDB instance,
e.g.
`MONGODB_URI=mongodb://bulkwriteuser:secret@mongodb:27017/bulkwritedb?authSource=bulkwritedb`

To build and run the Docker container, use the following commands:

```bash
 docker compose up -d --build
```

Then, you can run any script directly, e.g., to run the insert example:

```bash
 docker compose exec php php bulk_write_connect.php
```

You can enter the container shell for interactive use:

```bash
 docker compose exec php sh
```

Stop the containers when done:

```bash
 docker compose down
```

## Tips

Use environment variables for credentials (recommended).
Validate filters to avoid unintended mass updates/deletes.
Batch or Group related writes to reduce round trips.

## Next Steps

Explore MongoDB
docs: [Bulk Write](https://www.mongodb.com/docs/php-library/current/crud/bulk-write/) | [Error Handling](https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBCollection-bulkWrite/#errors-exceptions).
