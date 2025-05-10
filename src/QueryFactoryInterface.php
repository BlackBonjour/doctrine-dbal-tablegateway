<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

/**
 * Interface for query factory implementations.
 */
interface QueryFactoryInterface
{
    public function createBulkInsert(): BulkInsert;
}
