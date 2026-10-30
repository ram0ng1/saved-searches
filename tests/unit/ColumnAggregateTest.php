<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Tests\unit;

use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Ramon\SavedSearches\Database\ColumnAggregate;

class ColumnAggregateTest extends TestCase
{
    public function test_aplica_aspas_e_prefixo_de_tabela(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', 'flarum_');

        $sql = (new ColumnAggregate('max', 'posts.id', 'latest_id'))->getValue(new SQLiteGrammar($connection));

        $this->assertSame('max("flarum_posts"."id") as "latest_id"', $sql);
    }

    public function test_sem_prefixo(): void
    {
        $connection = new PostgresConnection(fn () => new PDO('sqlite::memory:'), 'flarum', '');

        $sql = (new ColumnAggregate('min', 'posts.id', 'post_id'))->getValue(new PostgresGrammar($connection));

        $this->assertSame('min("posts"."id") as "post_id"', $sql);
    }

    public function test_recusa_funcao_fora_da_lista(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ColumnAggregate('sum(1); drop table posts; --', 'posts.id', 'x');
    }
}
