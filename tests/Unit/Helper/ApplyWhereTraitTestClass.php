<?php

declare(strict_types=1);

namespace BlackBonjourTest\TableGateway\Unit\Helper;

use BlackBonjour\TableGateway\ApplyWhereTrait;
use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;

/**
 * Helper class for testing the ApplyWhereTrait.
 *
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
final class ApplyWhereTraitTestClass
{
    use ApplyWhereTrait;

    /**
     * Public method to access the protected applyWhere method from the trait.
     *
     * @param list<string|CompositeExpression>|string|null $where
     * @param list<mixed>|array<string, mixed>             $params
     *
     * @throws InvalidArgumentException
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    public function applyWherePublic(
        QueryBuilder $queryBuilder,
        array|string|null $where,
        array $params = [],
        array $types = [],
    ): void {
        $this->applyWhere($queryBuilder, $where, $params, $types);
    }
}
