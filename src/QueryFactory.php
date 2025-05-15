<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\Query\Delete;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Default implementation of the QueryFactoryInterface for creating query objects.
 *
 * It serves as the standard implementation used by TableGateway when no custom factory is provided.
 */
readonly class QueryFactory implements QueryFactoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws Exception If there's an error accessing the database platform.
     * @throws InvalidArgumentException If the database platform is not MySQL or MariaDB.
     */
    public function createBulkInsert(): BulkInsert
    {
        return new BulkInsert($this->connection);
    }

    public function createDelete(): Delete
    {
        return new Delete($this->connection);
    }
}
