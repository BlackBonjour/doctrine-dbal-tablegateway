# doctrine-dbal-tablegateway

> This is just my personal playground and therefore not intended for production use.
>

A PHP library that implements the TableGateway pattern for Doctrine DBAL, providing a simple and efficient way to
interact with database tables.

## Overview

This library offers a clean, object-oriented interface for database operations using the TableGateway pattern. It wraps
Doctrine DBAL to provide:

- Simple CRUD operations (Create, Read, Update, Delete)
- Efficient bulk operations for better performance
- Type-safe parameter handling
- Flexible query building
- Support for transactions

The TableGateway pattern encapsulates the database access logic for a single table, making your code more maintainable
and testable.

## Requirements

- PHP 8.3 or higher
- Doctrine DBAL

## Installation

```bash
composer require blackbonjour/doctrine-dbal-tablegateway
```

## Basic Example

```php
// Create a TableGateway for the "user" table
$userTable = new TableGateway($connection, 'user');

// Insert a record
$userTable->insert(['name' => 'john_doe', 'email' => 'john@example.com']);

// Select records
$users = $userTable->select('status = :status', ['status' => 'active'])->fetchAllAssociative();

// Update records
$userTable->update(['status' => 'inactive'], ['last_login < :date'], ['date' => '2023-01-01']);

// Delete records
$userTable->delete(['id' => 123]);
```

## Documentation

For detailed information on how to use this library, please see:

- [Usage Guide](docs/USAGE.md) — Comprehensive usage examples
- [Unit Tests](docs/UNIT-TESTS.md) — Running and understanding unit tests
- [Integration Tests](docs/INTEGRATION-TESTS.md) — Setting up and running integration tests

## License

This project is licensed under the MIT License — see the LICENSE file for details.
