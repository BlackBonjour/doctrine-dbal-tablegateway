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
                // Extract column names from the first row and use them as a template for validating all later rows
                $columnNames = array_keys($row);
            } elseif (array_keys($row) !== $columnNames) {
                // Ensure all rows have identical columns to prevent SQL errors and maintain data integrity
                throw new InvalidArgumentException('All rows must have the same columns.');
            }

            // Generate a placeholder string (e.g. `(?,?,?)`) with one `?` per column
            $values[] = sprintf('(%s)', implode(',', array_fill(0, count($row), '?')));

            // Convert the two-dimensional row data into a flat array of parameters and capture each parameter’s type
            foreach ($row as $column => $value) {
                $params[] = $value;

                if ($columnTypes) {
                    // Use the provided column type or default to `STRING` if not specified
                    $types[] = $columnTypes[$column] ?? ParameterType::STRING;
                }
            }
        }

        // Prepare the columns with proper quoting for SQL injection prevention, e.g. `id`,`name`,`email`
        $columns = implode(',', array_map($this->platform->quoteIdentifier(...), $columnNames));
        $tableName = $this->platform->quoteIdentifier($table);

        // Format: INSERT INTO `table_name` (`col1`,`col2`) VALUES (?,?), (?,?)
        $sql = sprintf(/** @lang text */ 'INSERT INTO %s (%s) VALUES %s', $tableName, $columns, implode(', ', $values));

        if ($updateOnDuplicateKey) {
            if ($this->platform instanceof MariaDBPlatform) {
                /*
                 * MariaDB does not have a row alias like MySQL 8.0 and above.
                 *
                 * Format: `col1` = VALUES(`col1`)
                 */
                $updateColumns = array_map(
                    fn(string $column): string => sprintf(
                        '%1$s = VALUES(%1$s)',
                        $this->platform->quoteIdentifier($column),
                    ),
                    // If no specific update columns are provided, update all columns
                    $updateColumns ?: $columnNames,
                );

                $sql .= sprintf(' ON DUPLICATE KEY UPDATE %s', implode(',', $updateColumns));
            } else {
                /*
                 * For MySQL, use the row alias syntax with a reference to the new row values. It's supported since MySQL 8.0.
                 *
                 * Format: `col1` = `new`.`col1`
                 */
                $alias = $this->platform->quoteIdentifier('new');
                $updateColumns = array_map(
                    fn(string $column): string => sprintf(
                        '%2$s = %1$s.%2$s',
                        $alias,
                        $this->platform->quoteIdentifier($column),
                    ),
                    $updateColumns ?: $columnNames,
                );

                // Format: AS `new` ON DUPLICATE KEY UPDATE `col1` = `new`.`col1`,`col2` = `new`.`col2`
                $sql .= sprintf(' AS %s ON DUPLICATE KEY UPDATE %s', $alias, implode(',', $updateColumns));
            }
        }

        return (int) $this->connection->executeStatement($sql, $params, $types);
    }
}
