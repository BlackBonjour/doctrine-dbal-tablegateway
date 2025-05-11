<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Query;

use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\Query\BulkUpdate;
use BlackBonjour\TableGateway\QueryFactoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Throwable;

final class BulkUpdateTest extends TestCase
{
    /**
     * Verifies the result of the `executeStatement` method by updating the target table using a temporary table and
     * ensuring the correct execution of the update logic.
     *
     * @throws Throwable
     */
    public function testBulkUpdate(): void
    {
        // Mock dependencies
        $bulkInsert = $this->createMock(BulkInsert::class);
        $bulkInsert
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                self::stringContains('temp_test_table_'),
                [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
                ['id' => ParameterType::INTEGER],
            )
            ->willReturn(2);

        $queryFactory = $this->createMock(QueryFactoryInterface::class);
        $queryFactory
            ->expects($this->once())
            ->method('createBulkInsert')
            ->willReturn($bulkInsert);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(2);

        $queryBuilder
            ->expects($this->once())
            ->method('innerJoin')
            ->with('`t1`', self::stringContains('temp_test_table_'), '`t2`', '`t1`.`id` = `t2`.`id`');

        $queryBuilder
            ->expects($this->once())
            ->method('set')
            ->with('`t1`.`name`', '`t2`.`name`');

        $queryBuilder
            ->expects($this->once())
            ->method('update')
            ->with('`test_table` AS `t1`');

        $platform = $this->createMock(AbstractMySQLPlatform::class);
        $platform
            ->expects($this->atLeastOnce())
            ->method('quoteIdentifier')
            ->willReturnCallback(static fn($column): string => sprintf('`%s`', $column));

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        // Test case
        $tableGateway = new BulkUpdate($connection, $queryFactory);

        self::assertSame(
            2,
            $tableGateway->executeStatement(
                'test_table',
                [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
                ['id'],
                ['id' => ParameterType::INTEGER],
            ),
        );
    }
}
