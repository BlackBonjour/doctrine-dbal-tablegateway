# Integration Tests for doctrine-dbal-tablegateway

This document describes how to set up and run integration tests for the doctrine-dbal-tablegateway library.

## Setup for Integration Testing

This repository includes configuration for running integration tests with MariaDB.

### Prerequisites

- Docker
- Docker Compose
- PHP 8.1 or higher with required extensions for Doctrine DBAL

### Setup

1. Copy the distribution files to their actual names:
   ```bash
   cp docker-compose.yml.dist docker-compose.yml
   cp .env.dist .env
   ```

2. Edit the `.env` file to set your database configuration:
   ```
   DB_DRIVER=pdo_mysql
   DB_HOST=127.0.0.1
   DB_NAME=tablegateway_test
   DB_PORT=3306
   DB_PASSWORD=your_password
   DB_ROOT_PASSWORD=your_root_password
   DB_USER=your_username
   ```

### Running Integration Tests

To run the integration tests:

1. Start the MariaDB container:
   ```bash
   docker-compose up -d
   ```

2. Run the PHPUnit tests:
   ```bash
   vendor/bin/phpunit --testsuite integration
   ```

### Configuration

The Docker setup includes:

1. **MariaDB 11.4**: A database server for integration testing.

### Environment Variables

The integration tests use the following environment variables from your `.env` file:

- `DB_DRIVER`: Database driver (default: pdo_mysql)
- `DB_HOST`: Database host (default: 127.0.0.1)
- `DB_NAME`: Database name
- `DB_PORT`: Database port (default: 3306)
- `DB_PASSWORD`: Database password
- `DB_USER`: Database username
- `DB_ROOT_PASSWORD`: Database root password (for container setup)

### Integration Tests

The integration tests are located at `tests/Integration/TableGatewayIntegrationTest.php`.

These tests:
- Create the necessary database tables and test data automatically
- Test basic CRUD operations (insert, select, update, delete)
- Test bulk operations (bulkInsert, bulkUpdate)
- Test query operations (count)
- Test advanced queries with conditions, ordering, and limits
- Test edge cases and error handling

The integration tests demonstrate:
- Connecting to the actual MariaDB database
- Performing all TableGateway operations with real database queries
- Testing with different parameter types and edge cases
- Cleaning up test data after tests

### Customizing the Setup

You can modify the Docker configuration to suit your specific testing needs:

- Edit `docker-compose.yml` to modify database settings or add additional services
- Edit `.env` to change database connection parameters
