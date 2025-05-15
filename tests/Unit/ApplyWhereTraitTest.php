<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Unit;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use BlackBonjourTest\TableGateway\Unit\Helper\ApplyWhereTraitTestClass;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Throwable;

final class ApplyWhereTraitTest extends TestCase
{
    /**
     * Verifies that `applyWhere` correctly applies an array of where conditions.
     *
     * @throws Throwable
     */
    public function testApplyWhereWithArrayWhere(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('where')->with('id = :id', 'name = :name');
        $queryBuilder->expects($this->once())->method('setParameters')->with(
            ['id' => 1, 'name' => 'John'],
            ['id' => ParameterType::INTEGER],
        );

        $testObject = new ApplyWhereTraitTestClass();
        $testObject->applyWherePublic(
            $queryBuilder,
            ['id = :id', 'name = :name'],
            ['id' => 1, 'name' => 'John'],
            ['id' => ParameterType::INTEGER],
        );
    }

    /**
     * Verifies that `applyWhere` correctly applies a CompositeExpression where condition.
     *
     * @throws Throwable
     */
    public function testApplyWhereWithCompositeExpressionWhere(): void
    {
        $compositeExpression = CompositeExpression::and('id = :id', 'name = :name');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('where')->with($compositeExpression);
        $queryBuilder->expects($this->once())->method('setParameters')->with(
            ['id' => 1, 'name' => 'John'],
            ['id' => ParameterType::INTEGER],
        );

        $testObject = new ApplyWhereTraitTestClass();
        $testObject->applyWherePublic(
            $queryBuilder,
            [$compositeExpression],
            ['id' => 1, 'name' => 'John'],
            ['id' => ParameterType::INTEGER],
        );
    }

    /**
     * Verifies that `applyWhere` doesn't call `setParameters` when `$params` is empty.
     *
     * @throws Throwable
     */
    public function testApplyWhereWithEmptyParams(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('where')->with('id = 1');
        $queryBuilder->expects($this->never())->method('setParameters');

        $testObject = new ApplyWhereTraitTestClass();
        $testObject->applyWherePublic($queryBuilder, 'id = 1');
    }

    /**
     * Verifies that `applyWhere` throws an exception for an invalid where format.
     *
     * @throws Throwable
     */
    public function testApplyWhereWithInvalidWhereFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Invalid WHERE clause format provided.');

        $testObject = new ApplyWhereTraitTestClass();
        /** @phpstan-ignore-next-line Pass an associative array as WHERE, which is invalid */
        $testObject->applyWherePublic($this->createMock(QueryBuilder::class), ['id' => 1]);
    }

    /**
     * Verifies that `applyWhere` does nothing when WHERE is null.
     *
     * @throws Throwable
     */
    public function testApplyWhereWithNullWhere(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->never())->method('where');
        $queryBuilder->expects($this->never())->method('setParameters');

        $testObject = new ApplyWhereTraitTestClass();
        $testObject->applyWherePublic($queryBuilder, null);
    }

    /**
     * Verifies that `applyWhere` correctly applies a string where condition.
     *
     * @throws Throwable
     */
    public function testApplyWhereWithStringWhere(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('where')->with('id = :id');
        $queryBuilder->expects($this->once())->method('setParameters')->with(
            ['id' => 1],
            ['id' => ParameterType::INTEGER],
        );

        $testObject = new ApplyWhereTraitTestClass();
        $testObject->applyWherePublic(
            $queryBuilder,
            'id = :id',
            ['id' => 1],
            ['id' => ParameterType::INTEGER],
        );
    }
}
