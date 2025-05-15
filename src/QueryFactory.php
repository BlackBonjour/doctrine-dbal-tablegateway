<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\Query\Delete;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Factory for creating query objects.
 */
readonly class QueryFactory implements QueryFactoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @throws Exception
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
