<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\Query\Delete;

/**
 * Interface for query factory implementations.
 */
interface QueryFactoryInterface
{
    public function createBulkInsert(): BulkInsert;

    public function createDelete(): Delete;
}
