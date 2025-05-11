# Integration Tests for doctrine-dbal-tablegateway

This document describes how to set up and run integration tests for the doctrine-dbal-tablegateway library.

## Docker Setup for Integration Testing

This repository includes Docker configuration for running integration tests with PHP 8.3 and MariaDB.

### Prerequisites

- Docker
- Docker Compose

### Setup

1. Copy the distribution files to their actual names:
   ```bash
   cp Dockerfile.dist Dockerfile
   cp docker-compose.yml.dist docker-compose.yml
   ```

### Running Integration Tests

To run the integration tests using Docker:

```bash
# Build and start the containers
docker-compose up --build

# The tests will run automatically once the containers are up
# To run tests again without rebuilding:
docker-compose run php vendor/bin/phpunit
```

### Configuration

The Docker setup includes:

1. **PHP 8.3 Environment**: A Docker container with PHP 8.3 and all necessary extensions for Doctrine DBAL.
2. **MariaDB 10.11**: A database server for integration testing.

### Environment Variables

The following environment variables are configured in the Docker setup:

- `DATABASE_URL=mysql://user:password@mariadb:3306/tablegateway_test`

### Integration Tests

The integration tests are located at `tests/Integration/TableGatewayIntegrationTest.php`.

These tests:
- Create the necessary database tables and test data automatically
- Test basic CRUD operations (insert, select, update, delete)
- Test bulk operations (bulkInsert, bulkUpdate)
- Test query operations (count)
- Test advanced queries with conditions, ordering, and limits
- Test edge cases and error handling

To run the integration tests:

```bash
docker-compose run php vendor/bin/phpunit tests/Integration
```

The integration tests demonstrate:
- Connecting to the actual MariaDB database
- Performing all TableGateway operations with real database queries
- Testing with different parameter types and edge cases
- Cleaning up test data after tests

### Customizing the Setup

You can modify the Docker configuration to suit your specific testing needs:

- Edit `Dockerfile` to add PHP extensions or change PHP configuration
- Edit `docker-compose.yml` to modify database settings or add additional services
