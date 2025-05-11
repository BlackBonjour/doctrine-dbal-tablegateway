<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Unit\Query;

use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\Query\BulkUpdate;
use BlackBonjour\TableGateway\QueryFactoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use LogicException;
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
    public function testExecuteStatement(): void
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

        $platform = $this->createMock(AbstractMySQLPlatform::class);
        $platform
            ->expects($this->once())
            ->method('getDropTemporaryTableSQL')
            ->willReturnCallback(static fn(string $table): string => sprintf('DROP TEMPORARY TABLE %s', $table));

        $platform
            ->expects($this->atLeastOnce())
            ->method('quoteIdentifier')
            ->willReturnCallback(static fn($column): string => sprintf('`%s`', $column));

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(
                function (string $sql): int {
                    if (preg_match(
                        '/UPDATE `test_table` AS `t1` INNER JOIN `temp_test_table_([a-z0-9]+)` AS `t2` ON `t1`.`id`=`t2`.`id` SET `t1`.`name`=`t2`.`name`/i',
                        $sql,
                    )) {
                        return 2;
                    }

                    if (preg_match('/DROP TEMPORARY TABLE temp_test_table_([a-z0-9]+)/i', $sql)) {
                        return 0;
                    }

                    throw new LogicException('SQL is unexpected: ' . $sql);
                },
            );

        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        // Test case
        $bulkUpdate = new BulkUpdate($connection, $queryFactory);

        self::assertSame(
            2,
            $bulkUpdate->executeStatement(
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
