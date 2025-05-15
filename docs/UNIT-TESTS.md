# Unit Tests for doctrine-dbal-tablegateway

This document describes how to run and understand the unit tests for the doctrine-dbal-tablegateway library.

## Overview

Unit tests verify the functionality of individual components in isolation, using mocks and stubs to replace dependencies. These tests ensure that each class and method works correctly on its own.

## Running Unit Tests

### Prerequisites

- PHP 8.3 or higher with required extensions
- Composer dependencies installed

### Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/BlackBonjour/doctrine-dbal-tablegateway.git
   cd doctrine-dbal-tablegateway
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

### Running Tests

To run all unit tests:

```bash
vendor/bin/phpunit --testsuite unit
```

To run a specific test class:

```bash
vendor/bin/phpunit tests/Unit/Path/To/TestClass.php
```

## Unit Test Structure

The unit tests are organized in the `tests/Unit` directory, mirroring the structure of the `src` directory:

### Main Components Tested

1. **TableGateway** (`TableGatewayTest.php`)
   - Tests the main TableGateway class functionality
   - Covers CRUD operations, query building, and error handling
   - Uses mocks for the DBAL Connection and related components

2. **QueryFactory** (`QueryFactoryTest.php`)
   - Tests the factory responsible for creating query objects
   - Verifies that the correct query objects are created with proper dependencies

3. **Query Components** (`Query/` directory)
   - **BulkInsertTest.php**: Tests bulk insert operations
   - **DeleteTest.php**: Tests delete query operations

## Test Approach

The unit tests follow these principles:

1. **Isolation**: Each test focuses on a single unit of functionality
2. **Mocking**: External dependencies are mocked to isolate the code being tested
3. **Coverage**: Tests aim to cover normal operation, edge cases, and error handling
4. **Readability**: Tests are structured to clearly show what's being tested and expected outcomes

## Example Test Case

Here's an example of how a test case is structured:

```php
public function testExecuteStatement(): void
{
    // Mock dependencies
    $connection = $this->createMock(Connection::class);
    // Configure mocks...
    
    // Create the object under test
    $bulkInsert = new BulkInsert($connection);
    
    // Execute the method being tested
    $result = $bulkInsert->executeStatement(
        'test_table',
        [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ],
        ['id' => ParameterType::INTEGER],
    );
    
    // Assert the expected outcome
    self::assertEquals(2, $result);
}
```

## Writing New Unit Tests

When adding new functionality to the library, follow these guidelines for writing unit tests:

1. Create a new test class in the appropriate directory under `tests/Unit`
2. Extend `PHPUnit\Framework\TestCase`
3. Mock all external dependencies
4. Test both successful operations and error conditions
5. Use descriptive test method names that explain what's being tested
6. Add PHPDoc comments to explain complex test scenarios

## PHPUnit Configuration

The unit tests are configured in `phpunit.xml` with the following settings:

- Bootstrap file: `tests/bootstrap.php`
- Test suite: `unit` (directory: `tests/Unit`)
- Source code coverage: `src` directory
