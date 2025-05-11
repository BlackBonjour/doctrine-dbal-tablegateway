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
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
readonly class TableGateway
{
    use ApplyWhereTrait;

    public AbstractPlatform $platform;
    public QueryFactoryInterface $queryFactory;

    /**
     * @throws Exception
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
        return $this->queryFactory
            ->createBulkInsert()
            ->executeQuery($this->table, $rows, $columnTypes, $updateOnDuplicateKey, $updateColumns);
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
        return $this->queryFactory
            ->createBulkUpdate()
            ->executeStatement($this->table, $rows, $joinColumns, $columnTypes);
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

        // Use build-in `delete` method if no array values are provided
        if (empty(array_filter($criteria, 'is_array'))) {
            return (int) $this->connection->delete($this->table, $criteria, $types);
        }

        // Use custom delete query if array values are provided
        $where = [];
        $params = [];
        $paramTypes = [];

        foreach ($criteria as $column => $value) {
            $where[] = sprintf('%1$s=:%1$s', $column);
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
}
