<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\Query\Delete;

/**
 * Defines a factory for creating query objects used by TableGateway.
 *
 * Implementations must instantiate each query object and configure it with the appropriate database connection and settings.
 */
interface QueryFactoryInterface
{
    /**
     * Creates a new BulkInsert query object for performing efficient multi-row inserts.
     *
     * @return BulkInsert A configured BulkInsert query object ready for use.
     */
    public function createBulkInsert(): BulkInsert;

    /**
     * Creates a new Delete query object for performing delete operations.
     *
     * @return Delete A configured Delete query object ready for use.
     */
    public function createDelete(): Delete;
}
