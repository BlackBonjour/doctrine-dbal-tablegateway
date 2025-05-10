<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\TableManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\TestCase;
use Throwable;

final class TableManagerTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function testCreateTable(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('getCreateTableSQL')
            ->with(self::isInstanceOf(Table::class))
            ->willReturn(['CREATE TABLE `test_table` (`id` INT)']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('CREATE TABLE `test_table` (`id` INT)');

        // Test case
        $tableManager = new TableManager($connection);

        self::assertTrue(
            $tableManager->createTable(
                'test_table',
                [$this->createMock(Column::class)],
                [],
                [],
                [],
                ['option1' => 'value1'],
            ),
        );
    }

    /**
     * @throws Throwable
     */
    public function testCreateTableWithEmptyColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No columns provided for table creation');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractPlatform::class));

        $tableManager = new TableManager($connection);
        $tableManager->createTable('test_table', []);
    }

    /**
     * @throws Throwable
     */
    public function testCreateTemporaryTable(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('getCreateTableSQL')
            ->with(self::isInstanceOf(Table::class))
            ->willReturn(['CREATE TABLE `temp_table` (`id` INT)']);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('CREATE TEMPORARY TABLE `temp_table` (`id` INT)');

        // Test case
        $tableManager = new TableManager($connection);

        self::assertTrue(
            $tableManager->createTemporaryTable(
                'temp_table',
                [$this->createMock(Column::class)],
                [],
                [],
                [],
                ['option1' => 'value1'],
            ),
        );
    }

    /**
     * @throws Throwable
     */
    public function testCreateTemporaryTableWithEmptyColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No columns provided for temporary table creation');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($this->createMock(AbstractPlatform::class));

        $tableManager = new TableManager($connection);
        $tableManager->createTemporaryTable('temp_table', []);
    }

    /**
     * @throws Throwable
     */
    public function testCreateTemporaryTableWithMultipleStatements(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('getCreateTableSQL')
            ->with(self::isInstanceOf(Table::class))
            ->willReturn(
                [
                    'COMMENT ON TABLE temp_table IS \'Temporary table\'',
                    'CREATE TABLE `temp_table` (`id` INT)',
                    'CREATE INDEX `idx_temp_table` ON `temp_table` (`id`)',
                ],
            );

        $executedStatements = [];
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection
            ->expects($this->exactly(3))
            ->method('executeStatement')
            ->willReturnCallback(
                function ($sql) use (&$executedStatements) {
                    $executedStatements[] = $sql;

                    return 1; // Return value doesn't matter for this test
                },
            );

        // Test case
        $tableManager = new TableManager($connection);

        self::assertTrue(
            $tableManager->createTemporaryTable(
                'temp_table',
                [$this->createMock(Column::class)],
                [],
                [],
                [],
                ['option1' => 'value1'],
            ),
        );

        self::assertSame(
            [
                'COMMENT ON TABLE temp_table IS \'Temporary table\'',
                'CREATE TEMPORARY TABLE `temp_table` (`id` INT)',
                'CREATE INDEX `idx_temp_table` ON `temp_table` (`id`)',
            ],
            $executedStatements,
        );
    }

    /**
     * @throws Throwable
     */
    public function testDropTable(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('quoteIdentifier')
            ->with('test_table')
            ->willReturn('`test_table`');

        $platform
            ->expects($this->once())
            ->method('getDropTableSQL')
            ->with('`test_table`')
            ->willReturn('DROP TABLE `test_table`');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('DROP TABLE `test_table`');

        // Test case
        $tableManager = new TableManager($connection);

        self::assertTrue($tableManager->dropTable('test_table'));
    }

    /**
     * @throws Throwable
     */
    public function testDropTableIfExists(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('quoteIdentifier')
            ->with('test_table')
            ->willReturn('`test_table`');

        $platform
            ->expects($this->never())
            ->method('getDropTableSQL');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('DROP TABLE IF EXISTS `test_table`');

        // Test case
        $tableManager = new TableManager($connection);

        self::assertTrue($tableManager->dropTable('test_table', true));
    }

    /**
     * @throws Throwable
     */
    public function testDropTemporaryTable(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('quoteIdentifier')
            ->with('temp_table')
            ->willReturn('`temp_table`');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('DROP TEMPORARY TABLE `temp_table`');

        // Test case
        $tableManager = new TableManager($connection);

        self::assertTrue($tableManager->dropTemporaryTable('temp_table'));
    }

    /**
     * @throws Throwable
     */
    public function testDropTemporaryTableIfExists(): void
    {
        // Mock dependencies
        $platform = $this->createMock(AbstractPlatform::class);
        $platform
            ->expects($this->once())
            ->method('quoteIdentifier')
            ->with('temp_table')
            ->willReturn('`temp_table`');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $connection
            ->expects($this->once())
            ->method('executeStatement')
            ->with('DROP TEMPORARY TABLE IF EXISTS `temp_table`');

        // Test case
        $tableManager = new TableManager($connection);

        self::assertTrue($tableManager->dropTemporaryTable('temp_table', true));
    }
}
