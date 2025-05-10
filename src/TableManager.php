<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Table;
use SensitiveParameter;

/**
 * A manager class for handling database table operations such as creation and deletion.
 */
readonly class TableManager implements TableManagerInterface
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

    public function createTable(
        string $tableName,
        #[SensitiveParameter]
        array $columns,
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = [],
    ): bool {
        if (empty($columns)) {
            throw new InvalidArgumentException('No columns provided for table creation');
        }

        $sqlStatements = $this->platform->getCreateTableSQL(
            new Table($tableName, $columns, $indexes, $uniqueConstraints, $fkConstraints, $options),
        );

        foreach ($sqlStatements as $sql) {
            $this->connection->executeStatement($sql);
        }

        return true;
    }

    public function createTemporaryTable(
        string $tableName,
        #[SensitiveParameter]
        array $columns,
        array $indexes = [],
        array $uniqueConstraints = [],
        array $fkConstraints = [],
        array $options = [],
    ): bool {
        if (empty($columns)) {
            throw new InvalidArgumentException('No columns provided for temporary table creation');
        }

        $sqlStatements = $this->platform->getCreateTableSQL(
            new Table($tableName, $columns, $indexes, $uniqueConstraints, $fkConstraints, $options),
        );

        foreach ($sqlStatements as $sql) {
            // Modify the CREATE TABLE statement to CREATE TEMPORARY TABLE
            if (str_contains($sql, 'CREATE TABLE')) {
                $sql = str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $sql);
            }

            $this->connection->executeStatement($sql);
        }

        return true;
    }

    public function dropTable(string $tableName, bool $ifExists = false): bool
    {
        $quotedTableName = $this->platform->quoteIdentifier($tableName);

        if ($ifExists) {
            // Since Platform::getDropTableSQL doesn't support IF EXISTS, we need to construct the SQL manually
            $sql = sprintf(/** @lang text */ 'DROP TABLE IF EXISTS %s', $quotedTableName);
        } else {
            // Use the platform's getDropTableSQL for the standard case
            $sql = $this->platform->getDropTableSQL($quotedTableName);
        }

        $this->connection->executeStatement($sql);

        return true;
    }

    public function dropTemporaryTable(string $tableName, bool $ifExists = false): bool
    {
        $quotedTableName = $this->platform->quoteIdentifier($tableName);

        if ($ifExists) {
            // Construct the SQL manually with IF EXISTS clause
            $sql = sprintf(/** @lang text */ 'DROP TEMPORARY TABLE IF EXISTS %s', $quotedTableName);
        } else {
            // Construct the SQL manually for dropping temporary table
            $sql = sprintf(/** @lang text */ 'DROP TEMPORARY TABLE %s', $quotedTableName);
        }

        $this->connection->executeStatement($sql);

        return true;
    }
}
