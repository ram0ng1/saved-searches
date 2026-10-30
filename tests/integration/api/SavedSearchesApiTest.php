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

use Flarum\Group\Group;
use Flarum\User\User;
use Ramon\SavedSearches\Tests\integration\Support\SavedSearchesTestCase;

/**
 * Quem pode salvar, ver, mudar e apagar buscas.
 */
class SavedSearchesApiTest extends SavedSearchesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase($this->merge(
            [User::class => [$this->normalUser(), $this->author()]],
            ['saved_searches' => [$this->savedSearch(1, 3, 'da autora', ['q' => 'da autora'])]],
        ));
    }

    private function create(int $userId, array $attributes): \Psr\Http\Message\ResponseInterface
    {
        return $this->send($this->request('POST', '/api/saved-searches', [
            'authenticatedAs' => $userId,
            'json' => ['data' => ['type' => 'saved-searches', 'attributes' => $attributes]],
        ]));
    }

    public function test_visitante_nao_lista(): void
    {
        $response = $this->send($this->request('GET', '/api/saved-searches'));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_membro_salva_e_lista_so_as_proprias(): void
    {
        $response = $this->create(2, ['query' => '  flarum tag:dev ', 'filters' => ['q' => 'flarum', 'tag' => 'dev'], 'frequency' => 'weekly']);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $created = $this->json($response)['data']['attributes'];
        $this->assertSame('flarum tag:dev', $created['query']);
        $this->assertSame(['q' => 'flarum', 'tag' => 'dev'], $created['filters']);
        $this->assertSame('weekly', $created['frequency']);

        $list = $this->json($this->send($this->request('GET', '/api/saved-searches', ['authenticatedAs' => 2])));
        $this->assertCount(1, $list['data']);
        $this->assertSame('flarum tag:dev', $list['data'][0]['attributes']['query']);
    }

    public function test_nova_busca_so_considera_posts_futuros(): void
    {
        $this->prepareDatabase($this->discussion(1, 'Antiga', 'conteúdo antigo'));

        $id = $this->json($this->create(2, ['query' => 'antigo', 'filters' => ['q' => 'antigo']]))['data']['id'];

        $this->assertSame(10, $this->search((int) $id)->last_post_id);
    }

    public function test_sem_permissao_nao_salva(): void
    {
        $this->database()->table('group_permission')->where('permission', 'savedSearches.use')->delete();

        $response = $this->create(2, ['query' => 'flarum', 'filters' => ['q' => 'flarum']]);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_permissao_padrao_e_dos_membros(): void
    {
        $this->assertTrue(
            $this->database()->table('group_permission')
                ->where('permission', 'savedSearches.use')
                ->where('group_id', Group::MEMBER_ID)
                ->exists()
        );
    }

    public function test_nao_ve_nem_altera_busca_de_outra_pessoa(): void
    {
        $show = $this->send($this->request('PATCH', '/api/saved-searches/1', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'saved-searches', 'id' => '1', 'attributes' => ['frequency' => 'weekly']]],
        ]));
        $delete = $this->send($this->request('DELETE', '/api/saved-searches/1', ['authenticatedAs' => 2]));

        $this->assertEquals(404, $show->getStatusCode());
        $this->assertEquals(404, $delete->getStatusCode());
        $this->assertSame('immediately', $this->search(1)->frequency);
    }

    public function test_dono_muda_a_frequencia_e_apaga(): void
    {
        $update = $this->send($this->request('PATCH', '/api/saved-searches/1', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['type' => 'saved-searches', 'id' => '1', 'attributes' => ['frequency' => 'hourly']]],
        ]));
        $this->assertEquals(200, $update->getStatusCode(), (string) $update->getBody());
        $this->assertSame('hourly', $this->search(1)->frequency);

        $delete = $this->send($this->request('DELETE', '/api/saved-searches/1', ['authenticatedAs' => 3]));
        $this->assertEquals(204, $delete->getStatusCode());
        $this->assertNull($this->search(1));
    }

    public function test_respeita_o_limite_por_membro(): void
    {
        $this->setting('ramon-saved-searches.max_per_user', 2);

        $this->assertEquals(201, $this->create(2, ['query' => 'um', 'filters' => ['q' => 'um']])->getStatusCode());
        $this->assertEquals(201, $this->create(2, ['query' => 'dois', 'filters' => ['q' => 'dois']])->getStatusCode());

        $third = $this->create(2, ['query' => 'três', 'filters' => ['q' => 'três']]);
        $this->assertEquals(422, $third->getStatusCode());
    }

    public function test_recusa_busca_repetida(): void
    {
        $this->create(2, ['query' => 'flarum', 'filters' => ['q' => 'flarum']]);

        $this->assertEquals(422, $this->create(2, ['query' => 'flarum', 'filters' => ['q' => 'flarum']])->getStatusCode());
    }

    /** @return array<string, array{mixed}> */
    public static function invalidFilters(): array
    {
        return [
            'objeto aninhado' => [['q' => ['a' => 'b']]],
            'número'          => [['q' => 5]],
            'chave estranha'  => [['q; drop' => 'x']],
            'grande demais'   => [['q' => str_repeat('a', 1200)]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFilters')]
    public function test_recusa_filtros_invalidos(mixed $filters): void
    {
        $response = $this->create(2, ['query' => 'x', 'filters' => $filters]);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
    }

    public function test_recusa_frequencia_desconhecida(): void
    {
        $this->assertEquals(422, $this->create(2, ['query' => 'x', 'filters' => ['q' => 'x'], 'frequency' => 'monthly'])->getStatusCode());
    }

    public function test_forum_informa_permissao_e_limite(): void
    {
        $member = $this->json($this->send($this->request('GET', '/api', ['authenticatedAs' => 2])))['data']['attributes'];
        $guest = $this->json($this->send($this->request('GET', '/api')))['data']['attributes'];

        $this->assertTrue($member['canUseSavedSearches']);
        $this->assertSame(5, $member['savedSearchesMax']);
        $this->assertFalse($guest['canUseSavedSearches']);
    }
}
