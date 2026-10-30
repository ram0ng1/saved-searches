<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('saved_searches', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    // O texto que a pessoa digitou (com gambits) e os filtros da API que o
    // frontend montou a partir dele: é o que a busca agendada executa.
    $table->string('query', 255);
    $table->text('filters');
    $table->string('frequency', 16)->default('daily');
    // Posts até este id já foram considerados; a próxima busca olha só os seguintes.
    $table->unsignedInteger('last_post_id')->default(0);
    $table->dateTime('last_searched_at');
    $table->dateTime('last_notified_at')->nullable();
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->index(['frequency', 'last_searched_at']);
});
