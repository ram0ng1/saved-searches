<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Database;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use InvalidArgumentException;

/**
 * `min(coluna) as apelido` sem SQL cru: os nomes passam pelo wrap() da
 * gramática, que aplica o prefixo de tabela e as aspas do banco, e a função
 * vem de uma lista fixa.
 */
final class ColumnAggregate implements Expression
{
    private const FUNCTIONS = ['min', 'max'];

    public function __construct(
        private string $function,
        private string $column,
        private string $alias
    ) {
        if (! in_array($function, self::FUNCTIONS, true)) {
            throw new InvalidArgumentException("Unsupported aggregate: $function");
        }
    }

    public function getValue(Grammar $grammar): string
    {
        return $this->function.'('.$grammar->wrap($this->column).') as '.$grammar->wrap($this->alias);
    }
}
