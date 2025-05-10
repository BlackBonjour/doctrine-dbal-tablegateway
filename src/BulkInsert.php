<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Exception\QueryException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use SensitiveParameter;

/**
 * Class for handling bulk insert operations.
 */
readonly class BulkInsert
{
    public AbstractPlatform $platform;

    /**
     * @throws Exception
     */
    public function __construct(
        public Connection $connection,
    ) {
        $this->platform = $connection->getDatabasePlatform();
    }

    /**
     * Performs a bulk insert operation.
     *
     * @param string                                   $table                The table to insert into.
     * @param list<non-empty-array<string, mixed>>     $rows                 The rows to insert.
     * @param array<string, string|ParameterType|Type> $columnTypes          The types of the columns.
     * @param bool                                     $updateOnDuplicateKey Whether to update on a duplicate key.
     * @param array<string>                            $updateColumns        The columns to update on a duplicate key.
     *
     * @return int The number of affected rows.
     * @throws Exception
     * @throws QueryException
     */
    public function insert(
        string $table,
        #[SensitiveParameter]
        array $rows,
        array $columnTypes = [],
        bool $updateOnDuplicateKey = false,
        array $updateColumns = [],
    ): int {
        if (empty($rows)) {
            return 0;
        }

        $columnNames = null;
        $params = [];
        $types = [];
        $values = [];

        foreach ($rows as $row) {
            if ($columnNames === null) {
                $columnNames = array_keys($row);
            } elseif (array_keys($row) !== $columnNames) {
                throw new QueryException('All rows must have the same columns!');
            }

            $values[] = sprintf('(%s)', implode(',', array_fill(0, count($row), '?')));

            foreach ($row as $column => $value) {
                $params[] = $value;

                if ($columnTypes) {
                    $types[] = $columnTypes[$column] ?? ParameterType::STRING;
                }
            }
        }

        $columns = implode(',', array_map($this->platform->quoteIdentifier(...), $columnNames));
        $tableName = $this->platform->quoteIdentifier($table);

        $sql = sprintf(/** @lang text */ 'INSERT INTO %s (%s) VALUES %s', $tableName, $columns, implode(',', $values));

        if ($updateOnDuplicateKey) {
            $alias = $this->platform->quoteIdentifier('new');
            $updateColumns = array_map(
                fn(string $column): string => sprintf(
                    '%2$s=%s.%2$s',
                    $alias,
                    $this->platform->quoteIdentifier($column),
                ),
                $updateColumns ?: $columnNames,
            );

            $sql .= sprintf(' AS %s ON DUPLICATE KEY UPDATE %s', $alias, implode(',', $updateColumns));
        }

        return (int) $this->connection->executeStatement($sql, $params, $types);
    }
}
