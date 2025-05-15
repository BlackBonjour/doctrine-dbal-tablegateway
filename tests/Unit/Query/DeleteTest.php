<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Unit\Query;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Query\Delete;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Throwable;

final class DeleteTest extends TestCase
{
    /**
     * Tests the `executeStatement` method with no WHERE condition.
     *
     * @throws Throwable
     */
    public function testExecuteStatement(): void
    {
        // Mock dependencies
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('delete')
            ->with('test_table');

        $queryBuilder
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(5);

        $queryBuilder
            ->expects($this->never())
            ->method('setParameters');

        $queryBuilder
            ->expects($this->never())
            ->method('where');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Test case
        $delete = new Delete($connection);

        self::assertEquals(5, $delete->executeStatement('test_table', null));
    }

    /**
     * Tests the `executeStatement` method with an array of WHERE conditions.
     *
     * @throws Throwable
     */
    public function testExecuteStatementWithArrayWhere(): void
    {
        // Mock dependencies
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('delete')
            ->with('test_table');

        $queryBuilder
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $queryBuilder
            ->expects($this->once())
            ->method('setParameters')
            ->with(['id' => 1, 'name' => 'John'], ['id' => ParameterType::INTEGER]);

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('id = :id', 'name = :name');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Test case
        $delete = new Delete($connection);

        self::assertEquals(
            1,
            $delete->executeStatement(
                'test_table',
                ['id = :id', 'name = :name'],
                ['id' => 1, 'name' => 'John'],
                ['id' => ParameterType::INTEGER],
            ),
        );
    }

    /**
     * Tests the `executeStatement` method with a CompositeExpression WHERE condition.
     *
     * @throws Throwable
     */
    public function testExecuteStatementWithCompositeExpressionWhere(): void
    {
        // Mock dependencies
        $compositeExpression = CompositeExpression::and('id = :id', 'name = :name');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('delete')
            ->with('test_table');

        $queryBuilder
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $queryBuilder
            ->expects($this->once())
            ->method('setParameters')
            ->with(['id' => 1, 'name' => 'John'], ['id' => ParameterType::INTEGER]);

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with($compositeExpression);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Test case
        $delete = new Delete($connection);

        self::assertEquals(
            1,
            $delete->executeStatement(
                'test_table',
                [$compositeExpression],
                ['id' => 1, 'name' => 'John'],
                ['id' => ParameterType::INTEGER],
            ),
        );
    }

    /**
     * Tests the `executeStatement` method with an invalid WHERE condition.
     *
     * @throws Throwable
     */
    public function testExecuteStatementWithInvalidWhere(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Invalid WHERE clause format provided.');

        // Mock dependencies
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('delete')
            ->with('test_table');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Test case
        $delete = new Delete($connection);

        // Pass an associative array as WHERE, which is invalid
        $delete->executeStatement(
            'test_table',
            /** @phpstan-ignore-next-line */
            ['id' => 1],
        );
    }

    /**
     * Tests the `executeStatement` method with a string WHERE condition.
     *
     * @throws Throwable
     */
    public function testExecuteStatementWithStringWhere(): void
    {
        // Mock dependencies
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('delete')
            ->with('test_table');

        $queryBuilder
            ->expects($this->once())
            ->method('executeStatement')
            ->willReturn(1);

        $queryBuilder
            ->expects($this->once())
            ->method('setParameters')
            ->with(['id' => 1], ['id' => ParameterType::INTEGER]);

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('id = :id');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Test case
        $delete = new Delete($connection);

        self::assertEquals(
            1,
            $delete->executeStatement(
                'test_table',
                'id = :id',
                ['id' => 1],
                ['id' => ParameterType::INTEGER],
            ),
        );
    }
}
