<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\UniqueConstraint;
use SensitiveParameter;

/**
 * Interface for managing database table operations such as creation and deletion.
 */
interface TableManagerInterface
{
    /**
     * Creates a new database table with the specified name and columns.
     *
     * @param string                      $tableName         The name of the table to create.
     * @param array<Column>               $columns           The columns to add to the table.
     * @param array<Index>                $indexes           The indexes to add to the table.
     * @param array<UniqueConstraint>     $uniqueConstraints The unique constraints to add to the table.
     * @param array<ForeignKeyConstraint> $fkConstraints     The foreign key constraints to add to the table.
     * @param array<string, mixed>        $options           Additional table options.
     *
     * @return bool True if the table was created successfully, false otherwise.
     * @throws Exception When a database error occurs during table creation.
     * @throws InvalidArgumentException When no columns are provided (empty columns array).
     */
    public function createTable(
        string $tableName,
        #[SensitiveParameter]
        array $columns,
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = [],
    ): bool;

    /**
     * Creates a new temporary table with the specified name and columns.
     *
     * @param string                      $tableName         The name of the temporary table to create.
     * @param array<Column>               $columns           The columns to add to the table.
     * @param array<Index>                $indexes           The indexes to add to the table.
     * @param array<UniqueConstraint>     $uniqueConstraints The unique constraints to add to the table.
     * @param array<ForeignKeyConstraint> $fkConstraints     The foreign key constraints to add to the table.
     * @param array<string, mixed>        $options           Additional table options.
     *
     * @return bool True if the temporary table was created successfully, false otherwise.
     * @throws Exception When a database error occurs during temporary table creation.
     * @throws InvalidArgumentException When no columns are provided (empty columns array).
     */
    public function createTemporaryTable(
        string $tableName,
        #[SensitiveParameter]
        array $columns,
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = [],
    ): bool;

    /**
     * Drops a database table with the specified name.
     *
     * @param string $tableName The name of the table to drop.
     * @param bool   $ifExists  Whether to add an IF EXISTS clause to the DROP TABLE statement.
     *
     * @return bool True if the table was dropped successfully, false otherwise.
     * @throws Exception When a database error occurs during table deletion or if the table doesn't exist (when ifExists is false).
     */
    public function dropTable(string $tableName, bool $ifExists = false): bool;

    /**
     * Drops a temporary table with the specified name.
     *
     * @param string $tableName The name of the temporary table to drop.
     * @param bool   $ifExists  Whether to add an IF EXISTS clause to the DROP TEMPORARY TABLE statement.
     *
     * @return bool True if the temporary table was dropped successfully, false otherwise.
     * @throws Exception When a database error occurs during temporary table deletion or if the temporary table doesn't exist (when ifExists is false).
     */
    public function dropTemporaryTable(string $tableName, bool $ifExists = false): bool;
}
