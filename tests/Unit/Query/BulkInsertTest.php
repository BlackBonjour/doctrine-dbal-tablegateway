<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Unit\Query;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Query\BulkInsert;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use PHPUnit\Framework\TestCase;
use Throwable;

final class BulkInsertTest extends TestCase
{
    /**
     * Verifies that the `executeQuery` method inserts rows correctly and returns the total affected row count.
     *
     * @throws Throwable
     */
    public function testExecuteQuery(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractMySQLPlatform::class);
        $platform
            ->expects($this->exactly(3))
            ->method('quoteIdentifier')
            ->willReturnCallback(static fn(string $identifier): string => sprintf('`%s`', $identifier));

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO `test_table` (`id`,`name`) VALUES (?,?), (?,?)',
                [1, 'John Doe', 2, 'Jane Doe'],
                [],
            )
            ->willReturn(2);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        // Test case
        $bulkInsert = new BulkInsert($connection);

        self::assertEquals(
            2,
            $bulkInsert->executeQuery(
                'test_table',
                [
                    ['id' => 1, 'name' => 'John Doe'],
                    ['id' => 2, 'name' => 'Jane Doe'],
                ],
            ),
        );
    }

    /**
     * Verifies that the `executeQuery` method throws an exception if columns in the rows do not match.
     *
     * @throws Throwable
     */
    public function testExecuteQueryThrowsExceptionForMismatchedColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('All rows must have the same columns!');

        // Mock dependencies
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeStatement');

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractMySQLPlatform::class));

        // Test case
        $bulkInsert = new BulkInsert($connection);
        $bulkInsert->executeQuery(
            'test_table',
            [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2], // Missing 'name' column
            ],
        );
    }

    /**
     * Verifies that the `executeQuery` method inserts/updates rows correctly and returns the total affected row count.
     *
     * @throws Throwable
     */
    public function testExecuteQueryUpdateOnDuplicateKey(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractMySQLPlatform::class);
        $platform
            ->expects($this->exactly(6))
            ->method('quoteIdentifier')
            ->willReturnCallback(static fn(string $identifier): string => sprintf('`%s`', $identifier));

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO `test_table` (`id`,`name`)'
                . ' VALUES (?,?), (?,?) AS `new`'
                . ' ON DUPLICATE KEY UPDATE `id` = `new`.`id`,`name` = `new`.`name`',
                [1, 'John Doe', 2, 'Jane Doe'],
                [],
            )
            ->willReturn(2);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        // Test case
        $bulkInsert = new BulkInsert($connection);

        self::assertEquals(
            2,
            $bulkInsert->executeQuery(
                'test_table',
                [
                    ['id' => 1, 'name' => 'John Doe'],
                    ['id' => 2, 'name' => 'Jane Doe'],
                ],
                updateOnDuplicateKey: true,
            ),
        );
    }

    /**
     * Verifies that the `executeQuery` method correctly handles column types.
     *
     * @throws Throwable
     */
    public function testExecuteQueryWithColumnTypes(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractMySQLPlatform::class);
        $platform
            ->expects($this->exactly(4))
            ->method('quoteIdentifier')
            ->willReturnCallback(static fn(string $identifier): string => sprintf('`%s`', $identifier));

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'INSERT INTO `test_table` (`id`,`name`,`score`) VALUES (?,?,?), (?,?,?)',
                [1, 'John Doe', 10.5, 2, 'Jane Doe', 12.0],
                [
                    ParameterType::INTEGER,
                    ParameterType::STRING,
                    ParameterType::STRING,
                    ParameterType::INTEGER,
                    ParameterType::STRING,
                    ParameterType::STRING,
                ],
            )
            ->willReturn(2);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        // Test case
        $bulkInsert = new BulkInsert($connection);

        self::assertEquals(
            2,
            $bulkInsert->executeQuery(
                'test_table',
                [
                    ['id' => 1, 'name' => 'John Doe', 'score' => 10.5],
                    ['id' => 2, 'name' => 'Jane Doe', 'score' => 12.0],
                ],
                [
                    'id' => ParameterType::INTEGER,
                    'name' => ParameterType::STRING,
                    'score' => ParameterType::STRING,
                ],
            ),
        );
    }

    /**
     * Verifies that the `executeQuery` method returns 0 when an empty array of rows is provided.
     *
     * @throws Throwable
     */
    public function testExecuteQueryWithEmptyRows(): void
    {
        // Mock dependencies
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('executeStatement');

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractMySQLPlatform::class));

        // Test case
        $bulkInsert = new BulkInsert($connection);

        self::assertEquals(0, $bulkInsert->executeQuery('test_table', []));
    }
}
