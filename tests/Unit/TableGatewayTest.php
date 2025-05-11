<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Unit;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Exception\ResultException;
use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\Query\BulkUpdate;
use BlackBonjour\TableGateway\Query\Delete;
use BlackBonjour\TableGateway\QueryFactoryInterface;
use BlackBonjour\TableGateway\TableGateway;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;
use Throwable;

final class TableGatewayTest extends TestCase
{
    /**
     * Verifies the `bulkInsert` method that the correct number of rows is inserted into the database.
     *
     * @throws Throwable
     */
    public function testBulkInsert(): void
    {
        // Mock dependencies
        $bulkInsert = $this->createMock(BulkInsert::class);
        $bulkInsert
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'test_table',
                [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']],
                ['id' => ParameterType::INTEGER],
                true,
                ['name'],
            )
            ->willReturn(2);

        $queryFactory = $this->createMock(QueryFactoryInterface::class);
        $queryFactory
            ->expects($this->once())
            ->method('createBulkInsert')
            ->willReturn($bulkInsert);

        // Test case
        $tableGateway = new TableGateway(
            $this->createMock(Connection::class),
            'test_table',
            $queryFactory,
        );

        self::assertSame(
            2,
            $tableGateway->bulkInsert(
                [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
                ['id' => ParameterType::INTEGER],
                true,
                ['name'],
            ),
        );
    }

    /**
     * Verifies the result of the `bulkUpdate` method by updating the target table using a temporary table and ensuring
     * the correct execution of the update logic.
     *
     * @throws Throwable
     */
    public function testBulkUpdate(): void
    {
        // Mock dependencies
        $bulkUpdate = $this->createMock(BulkUpdate::class);
        $bulkUpdate
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'test_table',
                [['id' => 1, 'name' => 'John'], ['id' => 2, 'name' => 'Jane']],
                ['id'],
                ['id' => ParameterType::INTEGER],
            )
            ->willReturn(2);

        $queryFactory = $this->createMock(QueryFactoryInterface::class);
        $queryFactory
            ->expects($this->once())
            ->method('createBulkUpdate')
            ->willReturn($bulkUpdate);

        // Test case
        $tableGateway = new TableGateway($this->createMock(Connection::class), 'test_table', $queryFactory);

        self::assertSame(
            2,
            $tableGateway->bulkUpdate(
                [
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ],
                ['id'],
                ['id' => ParameterType::INTEGER],
            ),
        );
    }

    /**
     * Verifies the result of the `count` method when no WHERE clause is provided.
     *
     * @throws Throwable
     */
    public function testCount(): void
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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

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
        $queryBuilder->expects($this->once())->method('setParameters')->with(['id' => 10]);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(0, $tableGateway->count());
    }

    /**
     * Verifies that the `createQueryBuilder` method correctly creates a query builder for the table.
     *
     * @throws Throwable
     */
    public function testCreateQueryBuilder(): void
    {
        // Mock dependencies
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('from')
            ->with('test_table')
            ->willReturnSelf();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Test case
        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertSame($queryBuilder, $tableGateway->createQueryBuilder());
    }

    /**
     * Verifies that the `delete` method deletes rows and returns the affected row count.
     *
     * @throws Throwable
     */
    public function testDelete(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('test_table', ['id' => 1])
            ->willReturn(1);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(1, $tableGateway->delete(['id' => 1]));
    }

    /**
     * Verifies that the `delete` method throws an exception when no criteria is provided and the strict flag is true.
     *
     * @throws Throwable
     */
    public function testDeleteThrowsExceptionWhenNoCriteriaProvidedAndStrict(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No criteria provided for deletion');

        $tableGateway = new TableGateway(
            $this->createMock(Connection::class),
            'test_table',
            $this->createMock(QueryFactoryInterface::class),
        );

        $tableGateway->delete();
    }

    /**
     * Verifies that the `delete` method deletes all rows when no criteria are provided and strict mode is disabled.
     *
     * @throws Throwable
     */
    public function testDeleteWithoutCriteriaWhenStrictIsFalse(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('test_table')
            ->willReturn(3);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(3, $tableGateway->delete(strict: false));
    }

    /**
     * Verifies that the `delete` method correctly handles array values in criteria.
     *
     * @throws Throwable
     */
    public function testDeleteWithArrayValues(): void
    {
        // Mock dependencies
        $delete = $this->createMock(Delete::class);
        $delete
            ->expects($this->once())
            ->method('executeStatement')
            ->with(
                'test_table',
                'id = :id',
                ['id' => [1, 2, 3]],
                ['id' => ArrayParameterType::INTEGER],
            )
            ->willReturn(3);

        $queryFactory = $this->createMock(QueryFactoryInterface::class);
        $queryFactory
            ->expects($this->once())
            ->method('createDelete')
            ->willReturn($delete);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->never())
            ->method('delete');

        // Test case
        $tableGateway = new TableGateway($connection, 'test_table', $queryFactory);

        self::assertEquals(
            3,
            $tableGateway->delete(
                ['id' => [1, 2, 3]],
                ['id' => ParameterType::INTEGER],
            ),
        );
    }

    /**
     * Verifies that the `delete` method correctly handles parameter types.
     *
     * @throws Throwable
     */
    public function testDeleteWithParameterTypes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('test_table', ['id' => 42], ['id' => ParameterType::INTEGER])
            ->willReturn(1);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(1, $tableGateway->delete(['id' => 42], ['id' => ParameterType::INTEGER]));
    }

    /**
     * Verifies that the `insert` method successfully inserts data and returns the affected row count.
     *
     * @throws Throwable
     */
    public function testInsert(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('test_table', ['id' => 1, 'name' => 'John Doe'])
            ->willReturn(1);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(1, $tableGateway->insert(['id' => 1, 'name' => 'John Doe']));
    }

    /**
     * Verifies that the `insert` method throws an exception when the insert operation fails.
     *
     * @throws Throwable
     */
    public function testInsertThrowsExceptionOnFailure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Insert operation failed!');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('test_table', ['id' => 1, 'name' => 'Invalid'])
            ->willThrowException(new InvalidArgumentException('Insert operation failed!'));

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));
        $tableGateway->insert(['id' => 1, 'name' => 'Invalid']);
    }

    /**
     * Verifies that the `insert` method correctly handles custom parameter types.
     *
     * @throws Throwable
     */
    public function testInsertWithParameterTypes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                'test_table',
                ['id' => 1, 'value' => 'test'],
                ['id' => ParameterType::INTEGER, 'value' => ParameterType::STRING],
            )
            ->willReturn(1);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(
            1,
            $tableGateway->insert(
                ['id' => 1, 'value' => 'test'],
                ['id' => ParameterType::INTEGER, 'value' => ParameterType::STRING],
            ),
        );
    }

    /**
     * Verifies the `select` method retrieves all columns without a WHERE clause.
     *
     * @throws Throwable
     */
    public function testSelect(): void
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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals($result, $tableGateway->select());
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
        $queryBuilder->expects($this->once())->method('setParameters')->with(['active' => 1]);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals($result, $tableGateway->select('is_active = :active', ['active' => 1]));
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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(
            $result,
            $tableGateway->select('id = :id', ['id' => 42], ['id' => ParameterType::INTEGER]),
        );
    }

    /**
     * Verifies the result of the `selectFirst` method without a WHERE clause.
     *
     * @throws Throwable
     */
    public function testSelectFirst(): void
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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(['id' => 1, 'name' => 'John Doe'], $tableGateway->selectFirst());
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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(
            ['id' => 4, 'status' => 'active'],
            $tableGateway->selectFirst(
                where: ['status = :status', 'updated IS NOT NULL'],
                params: ['status' => 'active'],
            ),
        );
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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

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

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));
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
        $queryBuilder->expects($this->once())->method('setParameters')->with(['id' => 2]);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(['id' => 2, 'name' => 'Jane Doe'], $tableGateway->selectFirst('id = :id', ['id' => 2]));
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
        $queryBuilder->expects($this->once())->method('setParameters')->with(['username' => 'testuser']);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createQueryBuilder')->willReturn($queryBuilder);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertNull($tableGateway->selectFirst(where: 'username = :username', params: ['username' => 'testuser']));
    }

    /**
     * Verifies that the `update` method successfully updates rows and returns the affected row count.
     *
     * @throws Throwable
     */
    public function testUpdate(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with('test_table', ['name' => 'Updated Name'], ['id' => 1])
            ->willReturn(1);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(1, $tableGateway->update(['name' => 'Updated Name'], ['id' => 1]));
    }

    /**
     * Verifies that the `update` method returns 0 when no rows match the update criteria.
     *
     * @throws Throwable
     */
    public function testUpdateNoAffectedRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with('test_table', ['email' => 'updated@test.com'], ['id' => 999])
            ->willReturn(0);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(0, $tableGateway->update(['email' => 'updated@test.com'], ['id' => 999]));
    }

    /**
     * Verifies that the `update` method correctly handles exception on failure.
     *
     * @throws Throwable
     */
    public function testUpdateThrowsExceptionOnFailure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Update operation failed!');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with('test_table', ['name' => 'Invalid Update'], ['id' => 1])
            ->willThrowException(new InvalidArgumentException('Update operation failed!'));

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));
        $tableGateway->update(['name' => 'Invalid Update'], ['id' => 1]);
    }

    /**
     * Verifies that the `update` method supports custom parameter types.
     *
     * @throws Throwable
     */
    public function testUpdateWithParameterTypes(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'test_table',
                ['value' => 'Updated Value'],
                ['id' => 42],
                ['id' => ParameterType::INTEGER],
            )
            ->willReturn(1);

        $tableGateway = new TableGateway($connection, 'test_table', $this->createMock(QueryFactoryInterface::class));

        self::assertEquals(
            1,
            $tableGateway->update(
                ['value' => 'Updated Value'],
                ['id' => 42],
                ['id' => ParameterType::INTEGER],
            ),
        );
    }
}
