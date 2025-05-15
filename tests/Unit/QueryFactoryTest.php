<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Unit;

use BlackBonjour\TableGateway\Query\BulkInsert;
use BlackBonjour\TableGateway\QueryFactory;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use PHPUnit\Framework\TestCase;
use Throwable;

final class QueryFactoryTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function testCreateBulkInsert(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getDatabasePlatform')->willReturn($this->createMock(AbstractMySQLPlatform::class));

        $queryFactory = new QueryFactory($connection);

        self::assertInstanceOf(BulkInsert::class, $queryFactory->createBulkInsert());
    }
}
