<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Exception\ResultException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Types\Type;
use SensitiveParameter;

/**
 * TableGateway provides a simple and efficient way to interact with database tables using Doctrine DBAL.
 *
 * This class implements the Table Gateway pattern, offering methods for common database operations like select, insert, update, delete, and bulk operations while abstracting
 * the complexity of SQL queries.
 *
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
readonly class TableGateway
{
    use ApplyWhereTrait;

    public AbstractPlatform $platform;
    public QueryFactoryInterface $queryFactory;

    /**
     * @throws Exception If there's an error accessing the database platform.
     */
    public function __construct(
        public Connection $connection,
        public string $table,
        ?QueryFactoryInterface $queryFactory = null,
    ) {
        $this->platform = $connection->getDatabasePlatform();
        $this->queryFactory = $queryFactory ?? new QueryFactory($connection);
    }

    /**
     * Performs a bulk insert operation, efficiently inserting multiple rows in a single query.
     *
     * This method is optimized for inserting large datasets and can optionally handle duplicate key conflicts by updating specified columns when a duplicate is encountered.
     *
     * @param list<non-empty-array<string, mixed>>     $rows                 The rows to insert, each as an associative array.
     * @param array<string, string|ParameterType|Type> $columnTypes          Optional type mapping for columns.
     * @param bool                                     $updateOnDuplicateKey Whether to update existing rows on a duplicate key.
     * @param list<string>                             $updateColumns        Columns to update when a duplicate key is found.
     *
     * @return int The number of affected rows.
     * @throws Exception If a database error occurs during the operation.
     * @throws InvalidArgumentException If the input data is invalid or inconsistent.
     */
    public function bulkInsert(
        #[SensitiveParameter]
        array $rows,
        array $columnTypes = [],
        bool $updateOnDuplicateKey = false,
        array $updateColumns = [],
    ): int {
        return $this->queryFactory
            ->createBulkInsert()
            ->executeQuery($this->table, $rows, $columnTypes, $updateOnDuplicateKey, $updateColumns);
    }

    /**
     * Counts the number of rows in the database table, optionally filtered by a WHERE clause.
     *
     * This method executes a COUNT query and returns the number of rows that match the specified criteria.
     * If no criteria are provided, it returns the total number of rows in the table.
     *
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause or conditions to filter the rows.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to the WHERE clause placeholders.
     * @param array                                        $types  Parameter types for the bound parameters (improves type safety).
     *
     * @return int The count of rows matching the criteria, or the total number of rows if no criteria are provided.
     * @throws Exception If a database error occurs during the query execution.
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    public function count(
        array|string|null $where = null,
        #[SensitiveParameter]
        array $params = [],
        array $types = [],
    ): int {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->select('COUNT(1)');

        $this->applyWhere($queryBuilder, $where, $params, $types);

        return (int) ($queryBuilder->executeQuery()->fetchFirstColumn()[0] ?? 0);
    }

    /**
     * Creates a new QueryBuilder instance pre-configured for this table.
     *
     * This method provides a convenient way to build custom queries against the table.
     * The returned QueryBuilder already has the FROM clause set to this table.
     *
     * @return QueryBuilder
     */
    public function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->from($this->table);

        return $queryBuilder;
    }

    /**
     * Deletes rows from the database table based on the specified criteria.
     *
     * This method supports both simple criteria and array values for IN conditions.
     * When array values are provided, it automatically constructs the appropriate query with IN operators.
     *
     * @param array<string, mixed>                                                                  $criteria The conditions that determine which records to delete. An empty array will delete all records unless $strict is true.
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types    An optional array specifying the types of the data values in the criteria.
     * @param bool                                                                                  $strict   If true, an exception is thrown when no criteria are provided. This is a safety measure to prevent accidental deletion of all records.
     *
     * @return int The number of rows deleted.
     * @throws Exception If a database error occurs during the operation.
     * @throws InvalidArgumentException If no criteria are provided and $strict is true, or if invalid parameter types are specified.
     */
    public function delete(array $criteria = [], array $types = [], bool $strict = true): int
    {
        if ($strict && empty($criteria)) {
            throw new InvalidArgumentException('No criteria provided for deletion!');
        }

        // Use the built-in `delete` method if no array values are provided
        if (empty(array_filter($criteria, 'is_array'))) {
            return (int) $this->connection->delete($this->table, $criteria, $types);
        }

        // Use custom delete query if array values are provided
        $where = [];
        $params = [];
        $paramTypes = [];

        foreach ($criteria as $column => $value) {
            $where[] = sprintf('%1$s = :%1$s', $column);
            $params[$column] = $value;
            $columnType = $types[$column] ?? null;

            if (is_array($value)) {
                if ($columnType === null) {
                    $paramTypes[$column] = ArrayParameterType::STRING;
                } else {
                    if ($columnType instanceof ParameterType) {
                        $paramType = $columnType;
                    } elseif ($columnType instanceof Type) {
                        $paramType = $columnType->getBindingType();
                    } else {
                        $paramType = Type::getType($columnType)->getBindingType();
                    }

                    $paramTypes[$column] = match ($paramType) {
                        ParameterType::ASCII => ArrayParameterType::ASCII,
                        ParameterType::BINARY => ArrayParameterType::BINARY,
                        ParameterType::INTEGER => ArrayParameterType::INTEGER,
                        ParameterType::STRING => ArrayParameterType::STRING,
                        default => throw new InvalidArgumentException(
                            sprintf('Invalid parameter type "%s" for column "%s"!', $paramType->name, $column),
                        ),
                    };
                }
            } elseif ($columnType) {
                $paramTypes[$column] = $columnType;
            }
        }

        return $this->queryFactory
            ->createDelete()
            ->executeStatement($this->table, implode(' AND ', $where), $params, $paramTypes);
    }

    /**
     * Inserts a single row of data into the database table.
     *
     * This method provides a simple way to insert a single record into the table. For inserting multiple records efficiently, use the `bulkInsert` method instead.
     *
     * @param array<string, mixed>                                                                  $data  The data to be inserted as an associative array where keys are column names and values are the data to insert.
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types An optional array specifying the types of the data values for improved type safety and handling.
     *
     * @return int The number of affected rows (typically 1 for successful inserts).
     * @throws Exception If a database error occurs during the insert operation.
     */
    public function insert(#[SensitiveParameter] array $data, array $types = []): int
    {
        return (int) $this->connection->insert($this->table, $data, $types);
    }

    /**
     * Retrieves rows from the database table with an optional WHERE clause filter.
     *
     * This method executes a SELECT query and returns a Result object that can be used to fetch the data in various formats (associative arrays, objects, etc.).
     * The query selects all columns (*) by default.
     *
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause or conditions to filter the rows. Can be a string, a list of conditions, or null for no filter.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to placeholders in the WHERE clause. Can be a sequential or associative array depending on the placeholders.
     * @param array                                        $types  Parameter types for the bound parameters to ensure proper type handling and security.
     *
     * @return Result A Result object that can be used to fetch the query results.
     * @throws Exception If a database error occurs during the query execution.
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    public function select(
        array|string|null $where = null,
        #[SensitiveParameter]
        array $params = [],
        array $types = [],
    ): Result {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->select('*');

        $this->applyWhere($queryBuilder, $where, $params, $types);

        return $queryBuilder->executeQuery();
    }

    /**
     * Retrieves the first row from the database table that matches the specified criteria.
     *
     * This method is a convenience wrapper around select() that returns only the first matching row.
     * It's useful when you expect a single result or are only interested in the first match.
     *
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause or conditions to filter the rows. Can be a string, a list of conditions, or null for no filter.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to placeholders in the WHERE clause.
     * @param array                                        $types  Parameter types for the bound parameters.
     * @param bool                                         $strict If true, throws an exception when more than one row is found. This ensures the query returns exactly one or zero results.
     *
     * @return array<string, mixed>|null The first matching row as an associative array, or NULL if no rows are found.
     * @throws Exception If a database error occurs during the query execution.
     * @throws ResultException If strict mode is enabled and the query returns more than one row.
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
                throw new ResultException(sprintf('Expected exactly one row, but got %d rows!', $rowCount));
            }
        }

        return $result->fetchAssociative() ?: null;
    }

    /**
     * Updates rows in the database table that match the specified criteria.
     *
     * This method allows you to update one or more columns in rows that match the given criteria.
     * If no criteria are provided, all rows in the table will be updated with the new data.
     *
     * @param array<string, mixed>                                                                  $data     The data to update as an associative array where keys are column names and values are the new data to set.
     * @param array<string, mixed>                                                                  $criteria An optional associative array defining the conditions for the update. Empty criteria will update all rows in the table.
     * @param array<int<0,max>, string|ParameterType|Type>|array<string, string|ParameterType|Type> $types    An optional array specifying the types of the data values and criteria for improved type safety and handling.
     *
     * @return int The number of rows affected by the update operation.
     * @throws Exception If a database error occurs during the update operation.
     */
    public function update(#[SensitiveParameter] array $data, array $criteria = [], array $types = []): int
    {
        return (int) $this->connection->update($this->table, $data, $criteria, $types);
    }
}
