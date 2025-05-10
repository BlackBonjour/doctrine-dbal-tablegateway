<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

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
}
