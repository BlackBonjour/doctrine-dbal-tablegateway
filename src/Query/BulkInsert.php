<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway\Query;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Types\Type;
use SensitiveParameter;

/**
 * Class for handling bulk insert operations with optimized performance.
 *
 * This class provides functionality to insert multiple rows in a single query, which is significantly more efficient than inserting row one by one.
 * It also supports "ON DUPLICATE KEY UPDATE" functionality for handling duplicate key conflicts.
 *
 * Note: Currently only supports MySQL and MariaDB database platforms.
 */
readonly class BulkInsert
{
    public AbstractPlatform $platform;

    /**
     * @throws Exception If there's an error accessing the database platform.
     * @throws InvalidArgumentException If the database platform is not MySQL or MariaDB.
     */
    public function __construct(
        public Connection $connection,
    ) {
        $this->platform = $connection->getDatabasePlatform();

        if (($this->platform instanceof AbstractMySQLPlatform) === false) {
            throw new InvalidArgumentException('Bulk insert is only supported for MySQL and MariaDB platforms.');
        }
    }

    /**
     * Performs a bulk insert operation, efficiently inserting multiple rows in a single query.
     *
     * This method constructs and executes an optimized INSERT query for multiple rows. It can optionally handle duplicate key conflicts by updating specified columns when a
     * duplicate is encountered using the "ON DUPLICATE KEY UPDATE" syntax.
     *
     * @param string                                   $table                The name of the table to insert into.
     * @param list<non-empty-array<string, mixed>>     $rows                 The rows to insert, each as an associative array where keys are column names and values are the data.
     * @param array<string, string|ParameterType|Type> $columnTypes          Optional type mapping for columns to ensure proper type handling and security.
     * @param bool                                     $updateOnDuplicateKey Whether to update existing rows when a duplicate key is encountered.
     * @param array<string>                            $updateColumns        The specific columns to update on a duplicate key. If empty, all columns will be updated.
     *
     * @return int The number of affected rows (inserted and updated).
     * @throws Exception If a database error occurs during the operation.
     * @throws InvalidArgumentException If the input data is invalid or inconsistent, or if rows are empty.
     */
    public function executeQuery(
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
                throw new InvalidArgumentException('All rows must have the same columns.');
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

        $sql = sprintf(/** @lang text */ 'INSERT INTO %s (%s) VALUES %s', $tableName, $columns, implode(', ', $values));

        if ($updateOnDuplicateKey) {
            if ($this->platform instanceof MariaDBPlatform) {
                $updateColumns = array_map(
                    fn(string $column): string => sprintf(
                        '%1$s = VALUES(%1$s)',
                        $this->platform->quoteIdentifier($column),
                    ),
                    $updateColumns ?: $columnNames,
                );

                $sql .= sprintf(' ON DUPLICATE KEY UPDATE %s', implode(',', $updateColumns));
            } else {
                $alias = $this->platform->quoteIdentifier('new');
                $updateColumns = array_map(
                    fn(string $column): string => sprintf(
                        '%2$s = %1$s.%2$s',
                        $alias,
                        $this->platform->quoteIdentifier($column),
                    ),
                    $updateColumns ?: $columnNames,
                );

                $sql .= sprintf(' AS %s ON DUPLICATE KEY UPDATE %s', $alias, implode(',', $updateColumns));
            }
        }

        return (int) $this->connection->executeStatement($sql, $params, $types);
    }
}
