# Usage Guide for doctrine-dbal-tablegateway

This document provides instructions on how to use the doctrine-dbal-tablegateway library for database operations.

## Overview

The doctrine-dbal-tablegateway library provides a TableGateway implementation for Doctrine DBAL, offering a simple and efficient way to perform database operations. It abstracts the complexity of SQL queries while providing powerful features like bulk operations.

## Installation

### Prerequisites

- PHP 8.3 or higher
- Doctrine DBAL library

### Composer Installation

```bash
composer require blackbonjour/doctrine-dbal-tablegateway
```

## Basic Usage

### Creating a TableGateway Instance

```php
use BlackBonjour\TableGateway\TableGateway;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

// Create a Doctrine DBAL Connection
$connection = DriverManager::getConnection(
    [
        'driver' => 'pdo_mysql',
        'host' => 'localhost',
        'user' => 'username',
        'password' => 'password',
        'dbname' => 'database',
    ],
);

// Create a TableGateway for a specific table
$userTable = new TableGateway($connection, 'users');
```

### Basic CRUD Operations

#### Insert

```php
// Insert a single row
$affectedRows = $userTable->insert(
    [
        'username' => 'john_doe',
        'email' => 'john@example.com',
        'created_at' => '2025-05-11 12:00:00',
    ],
);
```

#### Select

```php
// Select all rows
$result = $userTable->select();
$allUsers = $result->fetchAllAssociative();

// Select with a WHERE condition
$result = $userTable->select('username = :username', ['username' => 'john_doe']);
$users = $result->fetchAllAssociative();

// Select with multiple conditions
$result = $userTable->select(
    [
        'username = :username',
        'email = :email',
    ],
    [
        'username' => 'john_doe',
        'email' => 'john@example.com',
    ],
);
$users = $result->fetchAllAssociative();
```

#### Select First Row

```php
// Select first row with WHERE condition
$user = $userTable->selectFirst('username = :username', ['username' => 'john_doe']);

// With strict mode (throws exception if more than one row is found)
$user = $userTable->selectFirst('email = :email', ['email' => 'john@example.com'], strict: true);
```

#### Update

```php
// Update rows matching criteria
$affectedRows = $userTable->update(
    ['status' => 'active'], // Data to update
    ['user_id' => 123],     // Criteria
);
```

#### Delete

```php
// Delete rows matching criteria
$affectedRows = $userTable->delete(['user_id' => 123]);

// Delete rows with array values
$affectedRows = $userTable->delete(['user_id' => [1, 2, 3]]);
```

#### Count

```php
// Count all rows
$count = $userTable->count();

// Count with a WHERE condition
$count = $userTable->count('status = :status', ['status' => 'active']);
```

### Bulk Operations

#### Bulk Insert

```php
// Insert multiple rows at once
$rows = [
    ['username' => 'user1', 'email' => 'user1@example.com'],
    ['username' => 'user2', 'email' => 'user2@example.com'],
    ['username' => 'user3', 'email' => 'user3@example.com'],
];

$affectedRows = $userTable->bulkInsert($rows);

// With "ON DUPLICATE KEY UPDATE"
$affectedRows = $userTable->bulkInsert($rows, [], true, ['username', 'email']);
```

### Working with Types

You can specify column types for better type handling:

```php
use Doctrine\DBAL\ParameterType;

// Insert with types
$userTable->insert(
    ['user_id' => 123, 'is_active' => true],
    ['user_id' => ParameterType::INTEGER, 'is_active' => ParameterType::BOOLEAN]
);

// Update with types
$userTable->update(
    ['last_login' => '2025-05-11 12:00:00'],
    ['user_id' => 123],
    ['last_login' => ParameterType::STRING, 'user_id' => ParameterType::INTEGER]
);

// Bulk operations with types
$rows = [
    ['user_id' => 1, 'created_at' => '2025-05-11 12:00:00'],
    ['user_id' => 2, 'created_at' => '2025-05-11 12:00:00'],
];

$userTable->bulkInsert(
    $rows,
    [
        'user_id' => ParameterType::INTEGER,
        'created_at' => ParameterType::STRING,
    ],
);
```

### Custom Queries

You can create custom queries using the QueryBuilder:

```php
$queryBuilder = $userTable->createQueryBuilder();
$queryBuilder
    ->select('username', 'email')
    ->where('created_at > :date')
    ->setParameter('date', '2025-05-04 12:00:00', ParameterType::STRING)
    ->orderBy('username', 'ASC')
    ->setMaxResults(10);

$result = $queryBuilder->executeQuery();
$users = $result->fetchAllAssociative();
```

## Advanced Usage

### Transaction Support

The library supports transactions through the Doctrine DBAL Connection:

```php
$connection->beginTransaction();

try {
    $userTable->insert(['username' => 'new_user']);
    $profileTable->insert(['user_id' => $connection->lastInsertId()]);
    
    $connection->commit();
} catch (Exception $e) {
    $connection->rollBack();

    throw $e;
}
```

### Error Handling

The library throws exceptions for various error conditions:

- `InvalidArgumentException`: For invalid input parameters
- `ResultException`: For unexpected query results
- Doctrine DBAL exceptions: For database-related errors

```php
use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Exception\ResultException;
use Doctrine\DBAL\Exception as DBALException;

try {
    $user = $userTable->selectFirst('email = :email', ['email' => 'common@example.com'], [], true);
} catch (ResultException $e) {
    // Handle multiple rows found
} catch (DBALException $e) {
    // Handle database errors
}
```

## Best Practices

1. **Use Transactions**: Wrap related operations in transactions to ensure data consistency.
2. **Specify Types**: Always specify column types for better type handling and security.
3. **Handle Exceptions**: Properly catch and handle exceptions to prevent application crashes.
4. **Use Bulk Operations**: Use bulk operations for better performance when working with multiple rows.
5. **Limit Result Sets**: Use the QueryBuilder to limit result sets for better performance.
