<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use SensitiveParameter;

/**
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
readonly class TableGateway
{
    public function __construct(
        public Connection $connection,
        public string $table,
    ) {}

    /**
     * Counts the number of rows in a database table, optionally applying a conditional WHERE clause.
     *
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause to filter the rows to be counted.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to the WHERE clause.
     * @param array                                        $types  Parameter types for the bound parameters.
     *
     * @return int The count of rows matching the criteria, or the total number of rows if no criteria are provided.
     * @throws Exception
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    public function count(
        array|string|null $where = null,
        #[SensitiveParameter]
        array $params = [],
        array $types = [],
    ): int {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('COUNT(1)');
        $queryBuilder->from($this->table);

        if ($where) {
            if (is_string($where)) {
                $queryBuilder->where($where);
            } elseif (array_is_list($where)) {
                $queryBuilder->where(...$where);
            } else {
                throw new QueryException('Invalid WHERE clause!');
            }

            if ($params) {
                $queryBuilder->setParameters($params, $types);
            }
        }

        return (int) ($queryBuilder->executeQuery()->fetchFirstColumn()[0] ?? 0);
    }

    /**
     * Deletes rows from the database table based on the specified criteria.
     *
     * @param array<string, mixed>                                                                  $criteria The conditions that determine which records to delete. An empty array will delete all records unless $strict is true.
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types    An optional array specifying the types of the data values in the criteria.
     * @param bool                                                                                  $strict   If true, an exception is thrown when no criteria are provided.
     *
     * @return int The number of rows deleted.
     * @throws Exception
     * @throws QueryException If no criteria are provided and $strict is true.
     */
    public function delete(array $criteria = [], array $types = [], bool $strict = true): int
    {
        if ($strict && empty($criteria)) {
            throw new QueryException('No criteria provided for deletion');
        }

        return (int) $this->connection->delete($this->table, $criteria, $types);
    }

    /**
     * Inserts data into the database table.
     *
     * @param array<string, mixed>                                                                  $data  The data to be inserted into the table as an associative array.
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types An optional array specifying the types of the data values.
     *
     * @return int The number of affected rows.
     * @throws Exception
     */
    public function insert(#[SensitiveParameter] array $data, array $types = []): int
    {
        return (int) $this->connection->insert($this->table, $data, $types);
    }

    /**
     * Retrieves rows from a database table based on the specified columns and optional WHERE clause.
     *
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause to filter the rows to be retrieved.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to the WHERE clause.
     * @param array                                        $types  Parameter types for the bound parameters.
     *
     * @throws Exception
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    public function select(
        array|string|null $where = null,
        #[SensitiveParameter]
        array $params = [],
        array $types = [],
    ): Result {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->select('*');
        $queryBuilder->from($this->table);

        if ($where) {
            if (is_string($where)) {
                $queryBuilder->where($where);
            } elseif (array_is_list($where)) {
                $queryBuilder->where(...$where);
            } else {
                throw new QueryException('Invalid WHERE clause!');
            }

            if ($params) {
                $queryBuilder->setParameters($params, $types);
            }
        }

        return $queryBuilder->executeQuery();
    }

    /**
     * Retrieves the first row from a database table based on the specified columns, with an optional WHERE clause and an optional strict mode to enforce a single row.
     *
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause to filter the rows to be retrieved.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to the WHERE clause.
     * @param array                                        $types  Parameter types for the bound parameters.
     * @param bool                                         $strict Determines whether strict mode is enabled; if true, an exception is thrown for more than one row.
     *
     * @return array<string, mixed>|null The first row of the query as an associative array, or NULL if no rows are found.
     * @throws ResultException           If strict mode is enabled and the query returns more than one row.
     * @throws Exception
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    public function selectFirst(
        array|string|null $where = null,
        #[SensitiveParameter]
        array $params = [],
        array $types = [],
        bool $strict = false,
    ): ?array {
        $result = $this->select($where, $params, $types);

        if ($strict) {
            $rowCount = $result->rowCount();

            if ($rowCount > 1) {
                throw new ResultException(sprintf('Expected exactly one row, but got %d rows', $rowCount));
            }
        }

        return $result->fetchAssociative() ?: null;
    }

    /**
     * Updates rows in the database table based on the specified criteria.
     *
     * @param array<string, mixed>                                                                  $data     The data to be updated in the table as an associative array.
     * @param array<string, mixed>                                                                  $criteria An optional associative array defining the conditions for the update.
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types    An optional array specifying the types of the data values and criteria.
     *
     * @return int The number of affected rows.
     * @throws Exception
     */
    public function update(#[SensitiveParameter] array $data, array $criteria = [], array $types = []): int
    {
        return (int) $this->connection->update($this->table, $data, $criteria, $types);
    }
}
