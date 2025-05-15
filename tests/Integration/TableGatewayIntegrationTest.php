<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Integration;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Exception\ResultException;
use BlackBonjour\TableGateway\QueryFactory;
use BlackBonjour\TableGateway\TableGateway;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Integration tests for TableGateway class. These tests connect to a real database and execute actual queries.
 */
final class TableGatewayIntegrationTest extends TestCase
{
    private Connection $connection;

    private TableGateway $productGateway;
    private TableGateway $testEntityGateway;
    private TableGateway $userGateway;

    /**
     * Test `bulkInsert` operation.
     *
     * @throws Throwable
     */
    public function testBulkInsert(): void
    {
        // Perform a bulk insert and verify three rows were affected
        self::assertEquals(
            3,
            $this->productGateway->bulkInsert(
                [
                    [
                        'sku' => 'BULK-001',
                        'name' => 'Bulk Insert Product 1',
                        'price' => 19.99,
                        'stock_count' => 100,
                        'category' => 'Test Products',
                    ],
                    [
                        'sku' => 'BULK-002',
                        'name' => 'Bulk Insert Product 2',
                        'price' => 29.99,
                        'stock_count' => 50,
                        'category' => 'Test Products',
                    ],
                    [
                        'sku' => 'BULK-003',
                        'name' => 'Bulk Insert Product 3',
                        'price' => 39.99,
                        'stock_count' => 25,
                        'category' => 'Test Products',
                    ],
                ],
            ),
        );

        // Verify the data was inserted
        $result = $this->productGateway->select('category = :category', ['category' => 'Test Products']);

        self::assertCount(3, $result->fetchAllAssociative());

        // Verify specific product data
        $product1 = $this->productGateway->selectFirst(['sku = :sku'], ['sku' => 'BULK-001']);

        self::assertIsArray($product1);
        self::assertEquals('Bulk Insert Product 1', $product1['name']);
        self::assertEquals('19.99', $product1['price']);
        self::assertEquals(100, $product1['stock_count']);
    }

    /**
     * Test `bulkInsert` with parameter types.
     *
     * @throws Throwable
     */
    public function testBulkInsertWithParameterTypes(): void
    {
        // Perform a bulk insert with parameter types and verify two rows were affected
        self::assertEquals(
            2,
            $this->productGateway->bulkInsert(
                [
                    [
                        'sku' => 'BULK-TYPE-001',
                        'name' => 'Bulk Type Product 1',
                        'price' => 49.99,
                        'stock_count' => 75,
                    ],
                    [
                        'sku' => 'BULK-TYPE-002',
                        'name' => 'Bulk Type Product 2',
                        'price' => 59.99,
                        'stock_count' => 60,
                    ],
                ],
                [
                    'price' => ParameterType::STRING,
                    'stock_count' => ParameterType::INTEGER,
                ],
            ),
        );

        // Verify the data was inserted with correct types
        $product = $this->productGateway->selectFirst(['sku = :sku'], ['sku' => 'BULK-TYPE-001']);

        self::assertIsArray($product);
        self::assertEquals('49.99', $product['price']);
        self::assertEquals(75, $product['stock_count']);
    }

    /**
     * Test `bulkInsert` with the "UPDATE ON DUPLICATE KEY" option.
     *
     * @throws Throwable
     */
    public function testBulkInsertWithUpdateOnDuplicateKey(): void
    {
        // First, insert some products
        $this->productGateway->bulkInsert(
            [
                [
                    'sku' => 'UPSERT-001',
                    'name' => 'Original Product 1',
                    'price' => 10.99,
                    'stock_count' => 10,
                ],
                [
                    'sku' => 'UPSERT-002',
                    'name' => 'Original Product 2',
                    'price' => 20.99,
                    'stock_count' => 20,
                ],
            ],
        );

        // Now perform a bulk insert with the "UPDATE ON DUPLICATE KEY" option.
        self::assertEquals(
            5,
            $this->productGateway->bulkInsert(
                [
                    [
                        'sku' => 'UPSERT-001', // Existing SKU
                        'name' => 'Updated Product 1',
                        'price' => 15.99,
                        'stock_count' => 15,
                    ],
                    [
                        'sku' => 'UPSERT-002', // Existing SKU
                        'name' => 'Updated Product 2',
                        'price' => 25.99,
                        'stock_count' => 25,
                    ],
                    [
                        'sku' => 'UPSERT-003', // New SKU
                        'name' => 'New Product 3',
                        'price' => 30.99,
                        'stock_count' => 30,
                    ],
                ],
                updateOnDuplicateKey: true,
            ),
        );

        // Verify the data was updated for existing products and inserted for new ones
        $product1 = $this->productGateway->selectFirst(['sku = :sku'], ['sku' => 'UPSERT-001']);

        self::assertIsArray($product1);
        self::assertEquals('Updated Product 1', $product1['name']);
        self::assertEquals('15.99', $product1['price']);

        $product2 = $this->productGateway->selectFirst(['sku = :sku'], ['sku' => 'UPSERT-002']);

        self::assertIsArray($product2);
        self::assertEquals('Updated Product 2', $product2['name']);
        self::assertEquals('25.99', $product2['price']);

        $product3 = $this->productGateway->selectFirst(['sku = :sku'], ['sku' => 'UPSERT-003']);

        self::assertIsArray($product3);
        self::assertEquals('New Product 3', $product3['name']);
        self::assertEquals('30.99', $product3['price']);
    }

    /**
     * Test `count` operation.
     *
     * @throws Throwable
     */
    public function testCount(): void
    {
        // Count all records. At least 4 from initialization plus any we've added.
        self::assertGreaterThanOrEqual(4, $this->testEntityGateway->count());

        // Count with a WHERE clause. At least 2 from initialization.
        self::assertGreaterThanOrEqual(2, $this->testEntityGateway->count('status = :status', ['status' => 'active']));

        // Count with parameter types. At least 1 from initialization.
        self::assertGreaterThanOrEqual(
            1,
            $this->testEntityGateway->count(
                'status = :status',
                ['status' => 'inactive'],
                ['status' => ParameterType::STRING],
            ),
        );
    }

    /**
     * Test `createQueryBuilder` with a custom query.
     *
     * @throws Throwable
     */
    public function testCreateQueryBuilder(): void
    {
        // Get a query builder and build a custom query
        $queryBuilder = $this->testEntityGateway->createQueryBuilder();
        $queryBuilder
            ->select('name', 'email', 'score')
            ->where('score > :min_score')
            ->setParameter('min_score', 80)
            ->orderBy('score', 'DESC');

        // Execute the query and verify the results
        $result = $queryBuilder->executeQuery();
        $rows = $result->fetchAllAssociative();

        self::assertNotEmpty($rows);

        foreach ($rows as $row) {
            self::assertArrayHasKey('name', $row);
            self::assertArrayHasKey('email', $row);
            self::assertArrayHasKey('score', $row);
            self::assertGreaterThan(80.0, $row['score']);
        }

        // Verify the order (the highest score first)
        if (count($rows) >= 2) {
            self::assertGreaterThanOrEqual($rows[1]['score'], $rows[0]['score']);
        }
    }

    /**
     * Test `delete` operation.
     *
     * @throws Throwable
     */
    public function testDelete(): void
    {
        // First, insert a record to delete and fetch it afterward
        $this->testEntityGateway->insert(
            [
                'name' => 'Delete Test User',
                'email' => 'delete@test.com',
            ],
        );

        $row = $this->testEntityGateway->selectFirst(['email = :email'], ['email' => 'delete@test.com']);

        self::assertIsArray($row);

        // Delete the record and verify one row was affected
        self::assertEquals(1, $this->testEntityGateway->delete(['id' => $row['id']]));

        // Verify the record is gone
        $deletedRow = $this->testEntityGateway->selectFirst(['email = :email'], ['email' => 'delete@test.com']);

        self::assertNull($deletedRow);
    }

    /**
     * Test `delete` with strict mode.
     *
     * @throws Throwable
     */
    public function testDeleteStrictMode(): void
    {
        // This should throw an exception as no criteria is provided
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('No criteria provided');

        $this->testEntityGateway->delete();
    }

    /**
     * Test `delete` with parameter types.
     *
     * @throws Throwable
     */
    public function testDeleteWithParameterTypes(): void
    {
        // First, insert a record to delete and fetch it afterward
        $this->testEntityGateway->insert(
            [
                'name' => 'Parameter Delete Test',
                'email' => 'param.delete@test.com',
            ],
        );

        $row = $this->testEntityGateway->selectFirst(['email = :email'], ['email' => 'param.delete@test.com']);

        self::assertIsArray($row);

        // Delete it with parameter types and verify one row was affected
        self::assertEquals(
            1,
            $this->testEntityGateway->delete(
                ['id' => $row['id']],
                ['id' => ParameterType::INTEGER],
            ),
        );

        // Verify the record is gone
        self::assertNull(
            $this->testEntityGateway->selectFirst(['email = :email'], ['email' => 'param.delete@test.com']),
        );
    }

    /**
     * Test a basic `insert` operation.
     *
     * @throws Throwable
     */
    public function testInsert(): void
    {
        // Insert a test record and verify one row was affected
        self::assertEquals(
            1,
            $this->testEntityGateway->insert(
                [
                    'name' => 'Integration Test User',
                    'email' => 'integration@test.com',
                    'status' => 'active',
                    'score' => 99.5,
                ],
            ),
        );

        // Select the record we just inserted and verify the data
        $row = $this->testEntityGateway->selectFirst(['name = :name'], ['name' => 'Integration Test User']);

        self::assertIsArray($row);

        self::assertArrayHasKey('id', $row);
        self::assertIsNumeric($row['id']);
        self::assertGreaterThan(0, $row['id']);

        self::assertArrayHasKey('name', $row);
        self::assertEquals('Integration Test User', $row['name']);

        self::assertArrayHasKey('email', $row);
        self::assertEquals('integration@test.com', $row['email']);

        self::assertArrayHasKey('status', $row);
        self::assertEquals('active', $row['status']);

        self::assertArrayHasKey('score', $row);
        self::assertEquals('99.50', $row['score']);

        self::assertArrayHasKey('created_at', $row);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['created_at']);

        self::assertArrayHasKey('updated_at', $row);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['updated_at']);
    }

    /**
     * Test `insert` with parameter types.
     *
     * @throws Throwable
     */
    public function testInsertWithParameterTypes(): void
    {
        // Insert with explicit parameter types and verify one row was affected
        self::assertEquals(
            1,
            $this->testEntityGateway->insert(
                [
                    'name' => 'Parameter Types Test',
                    'score' => 88.5,
                ],
                [
                    'name' => ParameterType::STRING,
                    'score' => ParameterType::STRING, // Explicitly use STRING for a numeric value
                ],
            ),
        );

        // Select the record we just inserted and verify the data
        $row = $this->testEntityGateway->selectFirst(['name = :name'], ['name' => 'Parameter Types Test']);

        self::assertIsArray($row);
        self::assertArrayHasKey('name', $row);
        self::assertEquals('Parameter Types Test', $row['name']);
        self::assertEquals('88.50', $row['score']);
    }

    /**
     * Test basic `select` operation.
     *
     * @throws Throwable
     */
    public function testSelect(): void
    {
        // Select all active users and fetch all rows
        $result = $this->testEntityGateway->select('status = :status', ['status' => 'active']);
        $rows = $result->fetchAllAssociative();

        // Verify we have exactly 2 active users (from our test data)
        self::assertCount(2, $rows);

        // Define the expected active users
        $expectedRows = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'status' => 'active',
                'score' => '85.50',
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'status' => 'active',
                'score' => '92.00',
            ],
        ];

        // Verify each row has all expected fields and values
        foreach ($rows as $row) {
            // Check all required fields exist
            self::assertArrayHasKey('id', $row);
            self::assertIsNumeric($row['id']);
            self::assertGreaterThan(0, $row['id']);

            self::assertArrayHasKey('name', $row);
            self::assertArrayHasKey('email', $row);
            self::assertArrayHasKey('status', $row);
            self::assertArrayHasKey('score', $row);
            self::assertArrayHasKey('created_at', $row);
            self::assertArrayHasKey('updated_at', $row);

            self::assertEquals('active', $row['status']);

            // Find the matching expected row by email
            $matchedExpectedRow = null;

            foreach ($expectedRows as $expectedRow) {
                if ($expectedRow['email'] === $row['email']) {
                    $matchedExpectedRow = $expectedRow;
                    break;
                }
            }

            // Verify we found a matching expected row and verify all expected fields match
            self::assertNotNull($matchedExpectedRow, sprintf('No expected row found with email "%s"', $row['email']));

            foreach ($matchedExpectedRow as $key => $value) {
                self::assertEquals($value, $row[$key], sprintf('Field "%s" does not match expected value', $key));
            }
        }
    }

    /**
     * Test `selectFirst` operation.
     *
     * @throws Throwable
     */
    public function testSelectFirst(): void
    {
        // Select the first user with a specific email
        $row = $this->testEntityGateway->selectFirst('email = :email', ['email' => 'john@example.com']);

        self::assertIsArray($row);

        // Define the expected row
        $expected = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'status' => 'active',
            'score' => '85.50',
        ];

        // Check all required fields exist
        self::assertArrayHasKey('id', $row);
        self::assertIsNumeric($row['id']);
        self::assertGreaterThan(0, $row['id']);

        self::assertArrayHasKey('created_at', $row);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['created_at']);

        self::assertArrayHasKey('updated_at', $row);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['updated_at']);

        // Verify all expected fields match
        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $row, sprintf('Field "%s" is missing from the row', $key));
            self::assertEquals($value, $row[$key], sprintf('Field "%s" does not match expected value', $key));
        }
    }

    /**
     * Test `selectFirst` with strict mode.
     *
     * @throws Throwable
     */
    public function testSelectFirstStrictMode(): void
    {
        // This should work fine as there's only one user with this email
        $row = $this->testEntityGateway->selectFirst('email = :email', ['email' => 'john@example.com'], strict: true);

        self::assertIsArray($row);
        self::assertEquals('John Doe', $row['name']);

        // This should throw an exception as there are multiple active users
        $this->expectException(ResultException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Expected exactly one row, but got 2 rows.');

        $this->testEntityGateway->selectFirst('status = :status', ['status' => 'active'], strict: true);
    }

    /**
     * Test `update` operation.
     *
     * @throws Throwable
     */
    public function testUpdate(): void
    {
        // First, insert a record to update and fetch it afterward
        $this->testEntityGateway->insert(
            [
                'name' => 'Update Test User',
                'email' => 'update@test.com',
                'status' => 'pending',
                'score' => 50.0,
            ],
        );

        $originalRow = $this->testEntityGateway->selectFirst(['email = :email'], ['email' => 'update@test.com']);

        self::assertIsArray($originalRow);

        // Update the record and verify one row was affected
        self::assertEquals(
            1,
            $this->testEntityGateway->update(
                [
                    'status' => 'active',
                    'score' => 75.0,
                ],
                ['id' => $originalRow['id']],
            ),
        );

        // Get the updated record and verify the data was updated
        $updatedRow = $this->testEntityGateway->selectFirst(['id = :id'], ['id' => $originalRow['id']]);

        self::assertIsArray($updatedRow);
        self::assertEquals('Update Test User', $updatedRow['name']);
        self::assertEquals('update@test.com', $updatedRow['email']);
        self::assertEquals('active', $updatedRow['status']);
        self::assertEquals('75.00', $updatedRow['score']);
    }

    /**
     * Test `update` with parameter types.
     *
     * @throws Throwable
     */
    public function testUpdateWithParameterTypes(): void
    {
        // First, insert a record to update and fetch it afterward
        $this->testEntityGateway->insert(
            [
                'name' => 'Parameter Update Test',
                'email' => 'param.update@test.com',
                'score' => 60.0,
            ],
        );

        $originalRow = $this->testEntityGateway->selectFirst(['email = :email'], ['email' => 'param.update@test.com']);

        self::assertIsArray($originalRow);

        // Update with parameter types and verify one row was affected
        self::assertEquals(
            1,
            $this->testEntityGateway->update(
                ['score' => 80.0],
                ['id' => $originalRow['id']],
                [
                    'score' => ParameterType::STRING,
                    'id' => ParameterType::INTEGER,
                ],
            ),
        );

        // Get the updated record and verify the data was updated
        $updatedRow = $this->testEntityGateway->selectFirst(['id = :id'], ['id' => $originalRow['id']]);

        self::assertIsArray($updatedRow);
        self::assertEquals('80.00', $updatedRow['score']);
    }

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        // Get database connection parameters from environment variables
        $dbname = getenv('DB_NAME');
        $driver = getenv('DB_DRIVER');
        $host = getenv('DB_HOST');
        $password = getenv('DB_PASSWORD');
        $port = getenv('DB_PORT');
        $user = getenv('DB_USER');

        if (
            $dbname === false
            || $driver === false
            || $host === false
            || $password === false
            || $port === false
            || $user === false
        ) {
            throw new RuntimeException('Missing database configuration in environment variables.');
        }

        // Create a real database connection
        $this->connection = DriverManager::getConnection(
            [
                'charset' => 'utf8mb4',
                'dbname' => $dbname,
                'driver' => $driver,
                'host' => $host,
                'password' => $password,
                'port' => $port,
                'user' => $user,
            ],
        );

        // Create the test tables and insert initial test data
        $this->createTestTables();
        $this->insertTestData();

        // Create a query factory
        $queryFactory = new QueryFactory($this->connection);

        // Create table gateways for each test table
        $this->productGateway = new TableGateway($this->connection, 'product', $queryFactory);
        $this->testEntityGateway = new TableGateway($this->connection, 'test_entity', $queryFactory);
        $this->userGateway = new TableGateway($this->connection, 'user', $queryFactory);
    }

    /**
     * @throws Throwable
     */
    protected function tearDown(): void
    {
        // Clean up test data
        $this->productGateway->delete(strict: false);
        $this->testEntityGateway->delete(strict: false);
        $this->userGateway->delete(strict: false);

        // Close the connection
        $this->connection->close();
    }

    /**
     * Create the test tables needed for the integration tests.
     *
     * @throws Throwable
     */
    private function createTestTables(): void
    {
        // Drop tables if they exist to ensure a clean state
        $this->connection->executeStatement('DROP TABLE IF EXISTS `product`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `test_entity`');
        $this->connection->executeStatement('DROP TABLE IF EXISTS `user`');

        // Create a `product` table for bulk operations
        $this->connection->executeStatement(
            <<<SQL
CREATE TABLE `product` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(10,2) NOT NULL,
    `stock_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `category` VARCHAR(100) DEFAULT NULL,
    `is_featured` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
SQL,
        );

        // Create a `test_entity` for basic CRUD operations
        $this->connection->executeStatement(
            <<<SQL
CREATE TABLE `test_entity` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    `score` DECIMAL(10,2) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
SQL,
        );

        // Create a `user` table for more complex operations
        $this->connection->executeStatement(
            <<<SQL
CREATE TABLE `user` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) DEFAULT NULL,
    `role` ENUM('admin', 'user', 'guest') DEFAULT 'user',
    `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `login_number` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_login` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
SQL,
        );
    }

    /**
     * Insert initial test data into the test tables.
     *
     * @throws Throwable
     */
    private function insertTestData(): void
    {
        // Insert initial data into `product` table
        $this->connection->executeStatement(
            <<<SQL
INSERT INTO
    `product` (`sku`, `name`, `price`, `stock_count`, `category`, `is_featured`)
VALUES
    ('PROD-001', 'Smartphone X', 699.99, 50, 'Electronics', 1),
    ('PROD-002', 'Laptop Pro', 1299.99, 25, 'Electronics', 1),
    ('PROD-003', 'Wireless Headphones', 149.99, 100, 'Electronics', 0),
    ('PROD-004', 'Coffee Maker', 79.99, 30, 'Home Appliances', 0),
    ('PROD-005', 'Fitness Tracker', 89.99, 75, 'Wearables', 1)
;
SQL,
        );

        // Insert initial data into `test_entity`
        $this->connection->executeStatement(
            <<<SQL
INSERT INTO
    `test_entity` (`name`, `email`, `status`, `score`)
VALUES 
    ('John Doe', 'john@example.com', 'active', 85.5),
    ('Jane Smith', 'jane@example.com', 'active', 92.0),
    ('Bob Johnson', 'bob@example.com', 'inactive', 67.5),
    ('Alice Brown', 'alice@example.com', 'pending', 78.0)
;
SQL,
        );

        // Insert initial data into `user` table
        $this->connection->executeStatement(
            <<<SQL
INSERT INTO
    `user` (`username`, `email`, `password`, `first_name`, `last_name`, `role`)
VALUES
    ('admin', 'admin@example.com', 'hashed_password_1', 'Admin', 'User', 'admin'),
    ('jsmith', 'john.smith@example.com', 'hashed_password_2', 'John', 'Smith', 'user'),
    ('mjones', 'mary.jones@example.com', 'hashed_password_3', 'Mary', 'Jones', 'user'),
    ('guest', 'guest@example.com', 'hashed_password_4', NULL, NULL, 'guest')
;
SQL,
        );
    }
}
