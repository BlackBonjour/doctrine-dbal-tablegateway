<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway\Query;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\QueryFactoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Exception\InvalidTableName;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use SensitiveParameter;

/**
 * Class for handling bulk update operations. Currently only supports MySQL and MariaDB.
 */
readonly class BulkUpdate
{
    public AbstractPlatform $platform;
    public AbstractSchemaManager $schemaManager;

    /**
     * @throws Exception
     */
    public function __construct(
        public Connection $connection,
        public QueryFactoryInterface $queryFactory,
    ) {
        $this->platform = $connection->getDatabasePlatform();

        if (($this->platform instanceof AbstractMySQLPlatform) === false) {
            throw new InvalidArgumentException('Bulk update is only supported for MySQL and MariaDB platforms.');
        }

        $this->schemaManager = $this->platform->createSchemaManager($this->connection);
    }

    /**
     * Performs a bulk update operation using a temporary table.
     *
     * @param string                                   $table       The table to update into.
     * @param list<non-empty-array<string, mixed>>     $rows        The rows to be used for updating the table.
     * @param list<string>                             $joinColumns The columns to use for joining the temporary table with the actual table.
     * @param array<string, string|ParameterType|Type> $columnTypes The types of the columns in the temporary table.
     *
     * @return int The number of affected rows.
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function executeStatement(
        string $table,
        #[SensitiveParameter]
        array $rows,
        array $joinColumns,
        array $columnTypes = [],
    ): int {
        if (empty($rows)) {
            return 0;
        }

        // Fetch column names from rows
        $columnNames = $this->fetchColumnNames($rows, $joinColumns);

        // Create a temporary table and insert data into it
        $tempTable = $this->createTemporaryTable($table);
        $tempTableName = $tempTable->getName();

        $this->queryFactory->createBulkInsert()->executeQuery($tempTableName, $rows, $columnTypes);

        // Update the actual table by joining with the temporary table
        $quotedTableAlias = $this->platform->quoteIdentifier('t1');
        $quotedTempTableAlias = $this->platform->quoteIdentifier('t2');

        $joinConditions = array_map(
            fn(string $column): string => sprintf(
                '%2$s.%1$s = %3$s.%1$s',
                $this->platform->quoteIdentifier($column),
                $quotedTableAlias,
                $quotedTempTableAlias,
            ),
            $joinColumns,
        );

        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->update(sprintf('%s AS %s', $this->platform->quoteIdentifier($table), $quotedTableAlias));
        $queryBuilder->innerJoin(
            $quotedTableAlias,
            $tempTableName,
            $quotedTempTableAlias,
            implode(' AND ', $joinConditions),
        );

        foreach ($columnNames as $column) {
            // Skip join columns in the SET clause
            if (in_array($column, $joinColumns, true)) {
                continue;
            }

            $quotedColumn = $this->platform->quoteIdentifier($column);
            $queryBuilder->set(
                sprintf('%s.%s', $quotedTableAlias, $quotedColumn),
                sprintf('%s.%s', $quotedTempTableAlias, $quotedColumn),
            );
        }

        $affectedRows = $queryBuilder->executeStatement();

        // Drop the temporary table using TableManager
        $this->dropTemporaryTable($tempTableName);

        return (int) $affectedRows;
    }

    /**
     * @throws Exception
     * @throws InvalidTableName
     */
    private function createTemporaryTable(string $table): Table
    {
        $indexes = $this->schemaManager->listTableIndexes($table);
        $columns = $this->schemaManager->listTableColumns($table);

        $table = new Table(sprintf('temp_%s_%s', $table, uniqid()), $columns, $indexes, options: ['temporary' => true]);
        $this->schemaManager->createTable($table);

        return $table;
    }

    /**
     * @throws Exception
     */
    private function dropTemporaryTable(string $table): void
    {
        $this->connection->executeStatement($this->platform->getDropTemporaryTableSQL($table));
    }

    /**
     * @throws InvalidArgumentException
     */
    private function fetchColumnNames(array $rows, array $joinColumns): array
    {
        if (empty($joinColumns)) {
            throw new InvalidArgumentException('Join columns must be specified for bulk update!');
        }

        $columnNames = null;
        $joinColumns = array_values($joinColumns);

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

        return $columnNames;
    }
}
