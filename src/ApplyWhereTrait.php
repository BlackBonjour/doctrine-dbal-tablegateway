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
     * Applies WHERE conditions to a QueryBuilder instance.
     *
     * This method handles different formats of WHERE conditions and applies them to the provided QueryBuilder.
     * It supports string conditions, arrays of conditions, and null (no conditions).
     *
     * @param QueryBuilder                                 $queryBuilder The QueryBuilder to apply the WHERE conditions to.
     * @param list<string|CompositeExpression>|string|null $where        SQL WHERE clause or conditions to filter the rows. Can be a string, a list of conditions, or null for no filter.
     * @param list<mixed>|array<string, mixed>             $params       Parameters to bind to placeholders in the WHERE clause.
     * @param array                                        $types        Parameter types for the bound parameters to ensure proper type handling and security.
     *
     * @throws InvalidArgumentException If the WHERE clause format is invalid or unsupported.
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
            throw new InvalidArgumentException('Invalid WHERE clause format provided.');
        }

        if ($params) {
            $queryBuilder->setParameters($params, $types);
        }
    }
}
