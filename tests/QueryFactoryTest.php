<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway;

use BlackBonjour\TableGateway\BulkInsert;
use BlackBonjour\TableGateway\QueryFactory;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Throwable;

final class QueryFactoryTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function testCreateBulkInsert(): void
    {
        $queryFactory = new QueryFactory($this->createMock(Connection::class));

        self::assertInstanceOf(BulkInsert::class, $queryFactory->createBulkInsert());
    }
}
