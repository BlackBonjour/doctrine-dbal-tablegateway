<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway;

use BlackBonjour\TableGateway\ResultException;
use BlackBonjour\TableGateway\TableGateway;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Throwable;

final class TableGatewayTest extends TestCase
{
    /**
     * Verifies the result of the `count` method when no WHERE clause is provided.
     *
     * @throws Throwable
     */
    public function testCountWithoutWhereClause(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchFirstColumn')->willReturn([5]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('COUNT(1)');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(5, $tableGateway->count());
    }

    /**
     * Verifies the result of the `count` method when the query returns an empty result set.
     *
     * @throws Throwable
     */
    public function testCountWithEmptyResult(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchFirstColumn')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('COUNT(1)');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(0, $tableGateway->count());
    }

    /**
     * Verifies the result of the `count` method with specific bound parameter types.
     *
     * @throws Throwable
     */
    public function testCountWithParameterTypes(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchFirstColumn')->willReturn([5]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('COUNT(1)');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('where')->with('id = :id');
        $queryBuilder->expects($this->once())->method('setParameters')->with(
            ['id' => 10],
            ['id' => ParameterType::INTEGER],
        );
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(5, $tableGateway->count('id = :id', ['id' => 10], ['id' => ParameterType::INTEGER]));
    }

    /**
     * Verifies the result of the `count` method with a WHERE clause and bound parameters.
     *
     * @throws Throwable
     */
    public function testCountWithWhereClause(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchFirstColumn')->willReturn([3]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('COUNT(1)');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('where')->with('id = :id');
        $queryBuilder->expects($this->once())->method('setParameters')->with(['id' => 10], []);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(3, $tableGateway->count('id = :id', ['id' => 10]));
    }

    /**
     * Verifies the `count` method returns 0 when there are no matching rows.
     *
     * @throws Throwable
     */
    public function testCountWithZeroMatchingRows(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchFirstColumn')->willReturn([0]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('COUNT(1)');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(0, $tableGateway->count());
    }

    /**
     * Verifies the `select` method retrieves all columns without a WHERE clause.
     *
     * @throws Throwable
     */
    public function testSelectAllColumnsWithoutWhereClause(): void
    {
        $result = $this->createMock(Result::class);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals($result, $tableGateway->select());
    }

    /**
     * Verifies the `select` method retrieves specific columns without a WHERE clause.
     *
     * @throws Throwable
     */
    public function testSelectSpecificColumnsWithoutWhereClause(): void
    {
        $result = $this->createMock(Result::class);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('id, name, email');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals($result, $tableGateway->select('id, name, email'));
    }

    /**
     * Verifies the `select` method with a WHERE clause and bound parameters.
     *
     * @throws Throwable
     */
    public function testSelectWithWhereClauseAndParameters(): void
    {
        $result = $this->createMock(Result::class);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('where')->with('is_active = :active');
        $queryBuilder->expects($this->once())->method('setParameters')->with(['active' => 1], []);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals($result, $tableGateway->select('*', 'is_active = :active', ['active' => 1]));
    }

    /**
     * Verifies the `select` method retrieves rows with a WHERE clause and parameter types.
     *
     * @throws Throwable
     */
    public function testSelectWithWhereClauseAndParameterTypes(): void
    {
        $result = $this->createMock(Result::class);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('where')->with('id = :id');
        $queryBuilder->expects($this->once())->method('setParameters')->with(
            ['id' => 42],
            ['id' => ParameterType::INTEGER],
        );
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(
            $result,
            $tableGateway->select('*', 'id = :id', ['id' => 42], ['id' => ParameterType::INTEGER]),
        );
    }

    /**
     * Verifies the support for an array-based WHERE clause in the `selectFirst` method.
     *
     * @throws Throwable
     */
    public function testSelectFirstWithArrayWhereClause(): void
    {
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['id' => 4, 'status' => 'active']);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('where')->with('status = :status', 'updated IS NOT NULL');
        $queryBuilder->expects($this->once())->method('setParameters')->with(['status' => 'active']);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(
            ['id' => 4, 'status' => 'active'],
            $tableGateway->selectFirst(
                where: ['status = :status', 'updated IS NOT NULL'],
                params: ['status' => 'active'],
            ),
        );
    }

    /**
     * Verifies the result of the `selectFirst` method without a WHERE clause.
     *
     * @throws Throwable
     */
    public function testSelectFirstWithoutWhereClause(): void
    {
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['id' => 1, 'name' => 'John Doe']);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(['id' => 1, 'name' => 'John Doe'], $tableGateway->selectFirst());
    }

    /**
     * Verifies the `selectFirst` method returns NULL when no rows match the criteria.
     *
     * @throws Throwable
     */
    public function testSelectFirstWithoutWhereClauseReturnsNullIfNoRowMatches(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAssociative')->willReturn(false);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertNull($tableGateway->selectFirst());
    }

    /**
     * Verifies the `selectFirst` method returns the first row in strict mode when only one row is retrieved.
     *
     * @throws Throwable
     */
    public function testSelectFirstWithStrictMode(): void
    {
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $result
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['id' => 3, 'email' => 'example@test.com']);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(['id' => 3, 'email' => 'example@test.com'], $tableGateway->selectFirst(strict: true));
    }

    /**
     * Verifies the `selectFirst` method throws an exception in strict mode when multiple rows are found.
     *
     * @throws Throwable
     */
    public function testSelectFirstWithStrictModeThrowsExceptionWhenMoreThanOneRow(): void
    {
        $this->expectException(ResultException::class);
        $this->expectExceptionMessage('Expected exactly one row, but got 2 rows');

        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('rowCount')
            ->willReturn(2);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');
        $tableGateway->selectFirst(strict: true);
    }

    /**
     * Verifies the result of the `selectFirst` method with a WHERE clause and parameters.
     *
     * @throws Throwable
     */
    public function testSelectFirstWithWhereClause(): void
    {
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['id' => 2, 'name' => 'Jane Doe']);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('where')->with('id = :id');
        $queryBuilder->expects($this->once())->method('setParameters')->with(['id' => 2], []);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertEquals(['id' => 2, 'name' => 'Jane Doe'], $tableGateway->selectFirst('*', 'id = :id', ['id' => 2]));
    }

    /**
     * Verifies the `selectFirst` method with a WHERE clause returns NULL when no row matches the criteria.
     *
     * @throws Throwable
     */
    public function testSelectFirstWithWhereClauseReturnsNullIfNoRowMatches(): void
    {
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')->with('*');
        $queryBuilder->expects($this->once())->method('from')->with('test_table');
        $queryBuilder->expects($this->once())->method('where')->with('username = :username');
        $queryBuilder->expects($this->once())->method('setParameters')->with(['username' => 'testuser'], []);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table');

        self::assertNull($tableGateway->selectFirst(where: 'username = :username', params: ['username' => 'testuser']));
    }
}
