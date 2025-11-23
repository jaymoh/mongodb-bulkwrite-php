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
php bulk_write_insert.php
php bulk_write_update.php
php bulk_write_delete.php
php bulk_write_all.php

## Result Metrics
- getInsertedCount()
- getMatchedCount()
- getModifiedCount()
- getDeletedCount()

## Tips
Use environment variables for credentials (recommended).
Validate filters to avoid unintended mass updates/deletes.
Group related writes to reduce round trips.

## Next Steps
Add error handling granularity (write concern).
Explore transactions for multi-collection atomicity.
