// Switch to the bulkwritedb database
db = db.getSiblingDB('bulkwritedb');

// Create the database user with read/write permissions
db.createUser({
    user: 'bulkwriteuser',
    pwd: 'secret',
    roles: [
        {
            role: 'readWrite',
            db: 'bulkwritedb'
        }
    ]
});

// Create collections that will be used in the tutorial
db.createCollection('users');
db.createCollection('customers');

print('MongoDB initialization completed for bulkwritedb');
