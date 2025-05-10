<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Exception\ResultException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use SensitiveParameter;

/**
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
readonly class TableGateway
{
    public AbstractPlatform $platform;

    /**
     * @throws Exception
     */
    public function __construct(
        public Connection $connection,
        public string $table,
        public TableManagerInterface $tableManager,
    ) {
        $this->platform = $connection->getDatabasePlatform();
    }

    /**
     * Creates a new TableGateway instance with an optional TableManagerInterface.
     *
     * @param Connection                 $connection   The database connection.
     * @param string                     $table        The table name.
     * @param TableManagerInterface|null $tableManager The table manager (optional).
     *
     * @return self A new TableGateway instance.
     * @throws Exception
     */
    public static function create(
        Connection $connection,
        string $table,
        ?TableManagerInterface $tableManager = null,
    ): self {
        return new self(
            $connection,
            $table,
            $tableManager ?? new TableManager($connection),
        );
    }

    /**
     * Performs a bulk insert operation.
     *
     * @param list<non-empty-array<string, mixed>>     $rows
     * @param array<string, string|ParameterType|Type> $columnTypes
     * @param list<string>                             $updateColumns
     *
     * @return int The number of affected rows.
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function bulkInsert(
        #[SensitiveParameter]
        array $rows,
        array $columnTypes = [],
        bool $updateOnDuplicateKey = false,
        array $updateColumns = [],
    ): int {
        $bulkInsert = new BulkInsert($this->connection);

        return $bulkInsert->insert($this->table, $rows, $columnTypes, $updateOnDuplicateKey, $updateColumns);
    }

    /**
     * Performs a bulk update operation using a temporary table.
     *
     * @param list<non-empty-array<string, mixed>>     $rows        The rows to be used for updating the table.
     * @param list<string>                             $joinColumns The columns to use for joining the temporary table with the actual table.
     * @param array<string, string|ParameterType|Type> $columnTypes The types of the columns in the temporary table.
     *
     * @return int The number of affected rows.
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function bulkUpdate(
        #[SensitiveParameter]
        array $rows,
        array $joinColumns,
        array $columnTypes = [],
    ): int {
        if (empty($rows)) {
            return 0;
        }

        // Validate columns
        if (empty($joinColumns)) {
            throw new InvalidArgumentException('Join columns must be specified for bulk update!');
        }

        $columnNames = null;

        foreach ($rows as $row) {
            if ($columnNames === null) {
                $columnNames = array_keys($row);
            } elseif (array_keys($row) !== $columnNames) {
                throw new InvalidArgumentException('All rows must have the same columns!');
            }
        }

        if (array_intersect($joinColumns, $columnNames) !== $joinColumns) {
            throw new InvalidArgumentException('Join columns must be a subset of the columns in the rows!');
        }

        if (empty(array_diff($columnNames, $joinColumns))) {
            throw new InvalidArgumentException('Rows must not only contain join columns!');
        }

        // Create a temporary table using TableManager
        $columns = [];
        $tempTableName = sprintf('temp_%s_%s', $this->table, uniqid());

        foreach ($columnNames as $column) {
            $type = Type::getType(Types::STRING); // Default column type

            if (isset($columnTypes[$column])) {
                $columnType = $columnTypes[$column];

                if ($columnType instanceof Type) {
                    $type = $columnType;
                } elseif (is_string($columnType)) {
                    $type = Type::getType($columnType);
                }
            }

            $columns[] = new Column($column, $type);
        }

        $this->tableManager->createTemporaryTable(
            name: $tempTableName,
            columns: $columns,
            indexes: array_map(static fn(string $column): Index => new Index($column, [$column]), $joinColumns),
        );

        // Insert data into the temporary table
        $bulkInsert = new BulkInsert($this->connection);
        $bulkInsert->insert($tempTableName, $rows, $columnTypes);

        // Update the actual table by joining with the temporary table
        $joinConditions = array_map(
            fn(string $column): string => sprintf('t1.%1$s = t2.%1$s', $this->platform->quoteIdentifier($column)),
            $joinColumns,
        );

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->update(sprintf('%s AS t1', $this->table));
        $queryBuilder->innerJoin('t1', $tempTableName, 't2', implode(' AND ', $joinConditions));

        foreach ($columnNames as $column) {
            // Skip join columns in the SET clause
            if (in_array($column, $joinColumns, true)) {
                continue;
            }

            $quotedColumn = $this->platform->quoteIdentifier($column);
            $queryBuilder->set(sprintf('t1.%s', $quotedColumn), sprintf('t2.%s', $quotedColumn));
        }

        $affectedRows = $queryBuilder->executeStatement();

        // Drop the temporary table using TableManager
        $this->tableManager->dropTemporaryTable($tempTableName, true);

        return (int) $affectedRows;
    }

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
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->select('COUNT(1)');

        $this->applyWhere($queryBuilder, $where, $params, $types);

        return (int) ($queryBuilder->executeQuery()->fetchFirstColumn()[0] ?? 0);
    }

    public function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->from($this->table);

        return $queryBuilder;
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
     * @throws InvalidArgumentException If no criteria are provided and $strict is true.
     */
    public function delete(array $criteria = [], array $types = [], bool $strict = true): int
    {
        if ($strict && empty($criteria)) {
            throw new InvalidArgumentException('No criteria provided for deletion!');
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
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->select('*');

        $this->applyWhere($queryBuilder, $where, $params, $types);

        return $queryBuilder->executeQuery();
    }

    /**
     * Retrieves the first row from a database table based on the specified columns, with an optional WHERE clause and an optional strict mode to enforce a single row.
     *
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause to filter the rows to be retrieved.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to the WHERE clause.
     * @param array                                        $types  Parameter types for the bound parameters.
     * @param bool                                         $strict Determines whether a strict mode is enabled; if true, an exception is thrown for more than one row.
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
                throw new ResultException(sprintf('Expected exactly one row, but got %d rows!', $rowCount));
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

    /**
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause to filter the rows to be retrieved.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to the WHERE clause.
     * @param array                                        $types  Parameter types for the bound parameters.
     *
     * @throws InvalidArgumentException
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    private function applyWhere(
        QueryBuilder $queryBuilder,
        array|string|null $where,
        #[SensitiveParameter]
        array $params = [],
        array $types = [],
    ): void {
        if ($where === null) {
            return;
        }

        if (is_string($where)) {
            $queryBuilder->where($where);
        } elseif (array_is_list($where)) {
            $queryBuilder->where(...$where);
        } else {
            throw new InvalidArgumentException('Invalid WHERE clause!');
        }

        if ($params) {
            $queryBuilder->setParameters($params, $types);
        }
    }
}
