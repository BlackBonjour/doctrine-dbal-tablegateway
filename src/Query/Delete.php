<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway\Query;

use BlackBonjour\TableGateway\ApplyWhereTrait;
use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\CompositeExpression;

/**
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
readonly class Delete
{
    use ApplyWhereTrait;

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
     * Executes a delete statement on the specified table with the given conditions.
     *
     * @param string                                       $table  The name of the table to delete records from.
     * @param list<string|CompositeExpression>|string|null $where  The conditions to apply to the delete query. Can be an array, a string, or null for no conditions.
     * @param list<mixed>|array<string, mixed>             $params The parameters to bind to the query placeholders.
     * @param array                                        $types  The types of the parameters, if needed.
     *
     * @return int The number of rows affected by the delete statement.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    public function executeStatement(
        string $table,
        array|string|null $where,
        array $params = [],
        array $types = [],
    ): int {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->delete($table);

        $this->applyWhere($queryBuilder, $where, $params, $types);

        return (int) $queryBuilder->executeStatement();
    }
}
