<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use BlackBonjour\TableGateway\Exception\InvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use SensitiveParameter;

/**
 * @phpstan-import-type WrapperParameterTypeArray from Connection
 */
trait ApplyWhereTrait
{
    /**
     * @param list<string|CompositeExpression>|string|null $where  SQL WHERE clause to filter the rows to be retrieved.
     * @param list<mixed>|array<string, mixed>             $params Parameters to bind to the WHERE clause.
     * @param array                                        $types  Parameter types for the bound parameters.
     *
     * @throws InvalidArgumentException
     *
     * @phpstan-param WrapperParameterTypeArray            $types
     */
    private function applyWhere(
        QueryBuilder $queryBuilder,
        array|string|null $where,
        #[SensitiveParameter]
        array $params = [],
        array $types = [],
    ): void {
        if ($where === null) {
            return;
        }

        if (is_string($where)) {
            $queryBuilder->where($where);
        } elseif (array_is_list($where)) {
            $queryBuilder->where(...$where);
        } else {
            throw new InvalidArgumentException('Invalid WHERE clause!');
        }

        if ($params) {
            $queryBuilder->setParameters($params, $types);
        }
    }
}
