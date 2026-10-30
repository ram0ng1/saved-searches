<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Tests\integration\api;

use Carbon\Carbon;
use Flarum\User\User;
use Ramon\SavedSearches\Tests\integration\Support\SavedSearchesTestCase;

/**
 * A busca agendada: o que conta como novidade e para quem.
 *
 * Os casos usam o filtro `author` (sem fulltext) para valer em todos os
 * bancos. O fulltext do MySQL não enxerga linhas de uma transação aberta, e
 * cada teste roda dentro de uma; por isso o teste de texto livre roda só em
 * SQLite e PostgreSQL.
 */
class SavedSearchRunnerTest extends SavedSearchesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase($this->merge(
            [User::class => [$this->normalUser(), $this->author()]],
            $this->discussion(1, 'Primeira discussão', 'conteúdo antigo'),
            $this->discussion(2, 'Outra conversa', 'nada a ver', userId: 1),
        ));
    }

    public function test_avisa_resposta_nova_que_combina(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(1, $this->runSearches());

        $notifications = $this->notificationsFor(2);
        $this->assertCount(1, $notifications);
        $this->assertSame(1, $notifications[0]['subject_id']);
        $this->assertSame(['searchId' => 1, 'query' => 'author:autora', 'count' => 1, 'postNumber' => 2], $notifications[0]['data']);
        $this->assertSame(21, $this->search(1)->last_post_id);
    }

    public function test_nao_avisa_o_mesmo_post_duas_vezes(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->runSearches();
        $this->assertSame(0, $this->runSearches(Carbon::now()->addMinutes(10)));
        $this->assertCount(1, $this->notificationsFor(2));
    }

    public function test_conta_discussoes_com_novidade(): void
    {
        $this->prepareDatabase($this->merge(
            $this->discussion(3, 'Terceira', 'mais um', at: Carbon::now()),
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova'), $this->post(22, 1, 3, 'outra resposta')]],
        ));

        $this->runSearches();

        $data = $this->notificationsFor(2)[0]['data'];
        $this->assertSame(2, $data['count']);
    }

    public function test_ignora_os_proprios_posts(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 3, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'eu mesma')]],
        ));

        $this->assertSame(0, $this->runSearches());
        $this->assertSame(21, $this->search(1)->last_post_id);
    }

    public function test_ignora_o_que_ja_foi_lido(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
            ['discussion_user' => [['user_id' => 2, 'discussion_id' => 1, 'last_read_post_number' => 2, 'last_read_at' => Carbon::now()]]],
        ));

        $this->assertSame(0, $this->runSearches());
    }

    public function test_ignora_post_oculto_e_discussao_oculta(): void
    {
        $this->prepareDatabase($this->merge(
            $this->discussion(3, 'Escondida', 'texto', at: Carbon::now()),
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'oculto') + ['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]]],
        ));
        $this->database()->table('discussions')->where('id', 3)->update(['hidden_at' => Carbon::now(), 'hidden_user_id' => 1]);

        $this->assertSame(0, $this->runSearches());
    }

    public function test_espera_a_frequencia_vencer(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], 'daily', 20, Carbon::now()->subHours(3))]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(0, $this->runSearches());
        $this->assertSame(20, $this->search(1)->last_post_id);

        $this->assertSame(1, $this->runSearches(Carbon::now()->addDay()));
    }

    public function test_sem_permissao_nao_avisa(): void
    {
        $this->database()->table('group_permission')->where('permission', 'savedSearches.use')->delete();
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(0, $this->runSearches());
        $this->assertSame([], $this->notificationsFor(2));
    }

    /**
     * Mesma busca, mesmo resultado: cada pessoa tem a sua notificação. O
     * NotificationSyncer apagaria a de uma ao sincronizar a da outra se os
     * dados fossem iguais.
     */
    public function test_duas_pessoas_com_a_mesma_busca(): void
    {
        $this->prepareDatabase($this->merge(
            [User::class => [['id' => 4, 'username' => 'terceira', 'email' => 'terceira@machine.local', 'password' => 'too-obscure', 'is_email_confirmed' => 1]]],
            ['saved_searches' => [
                $this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20),
                $this->savedSearch(2, 4, 'author:autora', ['author' => 'autora'], lastPostId: 20),
            ]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(2, $this->runSearches());
        $this->assertCount(1, $this->notificationsFor(2));
        $this->assertCount(1, $this->notificationsFor(4));
    }

    public function test_filtro_quebrado_nao_trava_as_outras(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [
                $this->savedSearch(1, 2, 'quebrada', ['q' => ['não', 'é', 'texto']], lastPostId: 20),
                $this->savedSearch(2, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20),
            ]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(1, $this->runSearches());
        $this->assertSame(21, $this->search(1)->last_post_id);
    }

    /**
     * Na fila sync o e-mail sai dentro da rodada; um servidor de e-mail fora
     * do ar não pode desfazer o aviso que já apareceu no sino.
     */
    public function test_falha_no_email_nao_perde_o_aviso(): void
    {
        $this->setting('mail_driver', 'smtp');
        $this->setting('mail_host', '127.0.0.1');
        $this->setting('mail_port', '1');
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(1, $this->runSearches());
        $this->assertCount(1, $this->notificationsFor(2));
        $this->assertNotNull($this->search(1)->last_notified_at);
    }

    public function test_texto_livre(): void
    {
        $this->prepareDatabase($this->merge(
            $this->discussion(3, 'Sobre gatos', 'gatos gostam de caixas', at: Carbon::now()),
            ['saved_searches' => [$this->savedSearch(1, 2, 'gatos', ['q' => 'gatos'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta sem relação')]],
        ));

        // Depois do prepareDatabase: perguntar o banco já sobe o app e grava os dados.
        if ($this->isMysql()) {
            $this->markTestSkipped('O fulltext do MySQL não vê linhas da transação do teste.');
        }

        $this->assertSame(1, $this->runSearches());
        $notification = $this->notificationsFor(2)[0];
        $this->assertSame(3, $notification['subject_id']);
        $this->assertSame(1, $notification['data']['count']);
    }
}
