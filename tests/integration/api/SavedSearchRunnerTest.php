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
        // O JSON do MySQL reordena as chaves.
        $this->assertEquals(['searchId' => 1, 'query' => 'author:autora', 'count' => 1, 'postNumber' => 2], $notifications[0]['data']);
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
                $this->savedSearch(1, 2, 'quebrada', ['created' => ['não', 'é', 'data']], lastPostId: 20),
                $this->savedSearch(2, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20),
            ]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(1, $this->runSearches());
        $this->assertSame(21, $this->search(2)->last_post_id);
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

    /** Mais que uma página de resultados: nada se perde nem o total fica cortado. */
    public function test_nao_corta_em_50_discussoes(): void
    {
        $parts = [['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]]];
        for ($i = 0; $i < 60; $i++) {
            $parts[] = $this->discussion(100 + $i, "Discussão $i", 'texto', at: Carbon::now()->subMinutes(60 - $i));
        }
        $this->prepareDatabase($this->merge(...$parts));

        $this->runSearches();

        $this->assertSame(60, $this->notificationsFor(2)[0]['data']['count']);
    }

    /** Erro na rodada não pode pular o que chegou: a próxima tenta de novo. */
    public function test_erro_nao_avanca_o_marcador(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'quebrada', ['created' => ['não', 'é', 'data']], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->runSearches();

        $this->assertSame(20, $this->search(1)->last_post_id);
    }

    public function test_respeita_marcar_tudo_como_lido(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova', at: Carbon::now()->subMinute())]],
        ));
        $this->database()->table('users')->where('id', 2)->update(['marked_all_as_read_at' => Carbon::now()]);

        $this->assertSame(0, $this->runSearches());
    }

    public function test_filtro_booleano(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'is:unread author:autora', ['unread' => 'true', 'author' => 'autora'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(1, $this->runSearches());
    }

    /**
     * Rodada com orçamento de tempo: as buscas mais antigas vão primeiro e o
     * resto continua vencido para a próxima, em vez de a rodada encostar na
     * seguinte.
     */
    public function test_orcamento_de_tempo_deixa_o_resto_para_a_proxima(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [
                $this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20, lastSearchedAt: Carbon::now()->subDays(2)),
                $this->savedSearch(2, 2, 'resposta', ['q' => 'resposta'], lastPostId: 20, lastSearchedAt: Carbon::now()->subDays(5)),
            ]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $notified = $this->app()->getContainer()->make(\Ramon\SavedSearches\Search\SavedSearchRunner::class)->runDue(null, 0.0);

        $this->assertSame(1, $notified);
        $this->assertSame(21, $this->search(2)->last_post_id, 'a mais antiga roda primeiro');
        $this->assertSame(20, $this->search(1)->last_post_id, 'a outra fica para a próxima rodada');
    }

    /** Sem posts novos desde a última vez, a busca só ganha a hora, sem consulta própria. */
    public function test_busca_sem_novidade_so_atualiza_a_hora(): void
    {
        $this->prepareDatabase(['saved_searches' => [$this->savedSearch(1, 2, 'author:autora', ['author' => 'autora'], lastPostId: 20)]]);

        $this->assertSame(0, $this->runSearches());
        $this->assertTrue($this->search(1)->last_searched_at->gt(Carbon::now()->subMinute()));
    }

    public function test_texto_livre(): void
    {
        $this->prepareDatabase($this->merge(
            $this->discussion(3, 'Sobre bichos', 'gatos gostam de caixas', at: Carbon::now()),
            ['saved_searches' => [$this->savedSearch(1, 2, 'gatos', ['q' => 'gatos'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'resposta sem relação')]],
        ));

        $this->assertSame(1, $this->runSearches());
        $notification = $this->notificationsFor(2)[0];
        $this->assertSame(3, $notification['subject_id']);
        $this->assertSame(1, $notification['data']['count']);
    }

    /** Resposta sem as palavras conta se o título da discussão tem todas. */
    public function test_texto_no_titulo_da_discussao(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, 'primeira', ['q' => 'primeira'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'qualquer coisa')]],
        ));

        $this->assertSame(1, $this->runSearches());
    }

    public function test_todas_as_palavras_e_sem_caixa(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [
                $this->savedSearch(1, 2, 'GATOS caixas', ['q' => 'GATOS caixas'], lastPostId: 20),
                $this->savedSearch(2, 2, 'gatos cachorros', ['q' => 'gatos cachorros'], lastPostId: 20),
            ]],
            ['posts' => [$this->post(21, 1, 2, 'Os gatos adoram caixas')]],
        ));

        $this->assertSame(1, $this->runSearches());
        $this->assertSame(1, $this->notificationsFor(2)[0]['data']['searchId']);
    }

    /** Curingas do LIKE no texto não viram "qualquer coisa". */
    public function test_curingas_do_like_sao_texto(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [$this->savedSearch(1, 2, '%%', ['q' => '% _'], lastPostId: 20)]],
            ['posts' => [$this->post(21, 1, 2, 'qualquer coisa')]],
        ));

        // Sem palavras válidas a busca vira "qualquer novidade", como a busca vazia do fórum.
        $this->assertSame(1, $this->runSearches());
    }

    public function test_texto_com_outro_filtro(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [
                $this->savedSearch(1, 2, 'resposta author:autora', ['q' => 'resposta', 'author' => 'autora'], lastPostId: 20),
                $this->savedSearch(2, 2, 'resposta author:admin', ['q' => 'resposta', 'author' => 'admin'], lastPostId: 20),
            ]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        $this->assertSame(1, $this->runSearches());
        $this->assertSame(1, $this->notificationsFor(2)[0]['data']['searchId']);
    }

    public function test_sem_jit_do_postgresql(): void
    {
        $this->prepareDatabase($this->merge(
            ['saved_searches' => [
                $this->savedSearch(1, 2, 'resposta author:autora', ['q' => 'resposta', 'author' => 'autora'], lastPostId: 20),
            ]],
            ['posts' => [$this->post(21, 1, 2, 'resposta nova')]],
        ));

        if ($this->database()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('JIT só existe no PostgreSQL.');
        }

        if (version_compare((string) $this->database()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION), '11', '<')) {
            $this->markTestSkipped('O PostgreSQL só tem JIT a partir do 11.');
        }

        // As subconsultas de permissão passam do custo que liga o JIT; a
        // compilação levava segundos por busca num processador fraco.
        $jit = [];
        $this->database()->listen(function ($query) use (&$jit) {
            if (str_contains($query->sql, 'latest_id') || str_contains($query->sql, 'from "discussions"')) {
                $jit[] = $this->database()->selectOne('show jit')->jit;
            }
        });

        $this->assertSame(1, $this->runSearches());
        $this->assertNotEmpty($jit);
        $this->assertSame(['off'], array_values(array_unique($jit)));
    }
}
