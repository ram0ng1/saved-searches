<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Tests\integration\Support;

use Carbon\Carbon;
use Flarum\Notification\Notification;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface;
use Ramon\SavedSearches\Search\SavedSearchRunner;
use Ramon\SavedSearches\SavedSearch;

/**
 * Base dos testes: liga a extensão e oferece membros, discussões e posts.
 *
 * Usuário 1 é o admin; 2 (`normal`) salva buscas; 3 (`autora`) escreve.
 */
abstract class SavedSearchesTestCase extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('ramon-saved-searches');
        // Sem servidor de e-mail nos testes; a notificação também vai por e-mail.
        $this->setting('mail_driver', 'log');
    }

    /** @return array<string, mixed> */
    protected function author(): array
    {
        return ['id' => 3, 'username' => 'autora', 'email' => 'autora@machine.local', 'password' => 'too-obscure', 'is_email_confirmed' => 1];
    }

    /**
     * Uma discussão com o primeiro post já publicado (id = $id * 10).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function discussion(int $id, string $title, string $content, int $userId = 3, ?Carbon $at = null): array
    {
        $at ??= Carbon::now()->subDays(3);

        return [
            'discussions' => [[
                'id' => $id, 'title' => $title, 'slug' => 'd-'.$id, 'created_at' => $at, 'last_posted_at' => $at, 'user_id' => $userId,
                'first_post_id' => $id * 10, 'last_post_id' => $id * 10, 'comment_count' => 1, 'last_post_number' => 1, 'is_private' => 0,
            ]],
            'posts' => [$this->post($id * 10, $id, 1, $content, $userId, $at)],
        ];
    }

    /** @return array<string, mixed> */
    protected function post(int $id, int $discussionId, int $number, string $content, int $userId = 3, ?Carbon $at = null): array
    {
        return [
            'id' => $id, 'discussion_id' => $discussionId, 'number' => $number, 'created_at' => $at ?? Carbon::now(),
            'user_id' => $userId, 'type' => 'comment', 'content' => '<t><p>'.htmlspecialchars($content).'</p></t>', 'is_private' => 0,
        ];
    }

    /**
     * Busca salva já existente, como se tivesse sido criada pela API.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    protected function savedSearch(int $id, int $userId, string $query, array $filters, string $frequency = 'immediately', int $lastPostId = 0, ?Carbon $lastSearchedAt = null): array
    {
        return [
            'id' => $id, 'user_id' => $userId, 'query' => $query, 'filters' => json_encode($filters), 'frequency' => $frequency,
            'last_post_id' => $lastPostId, 'last_searched_at' => $lastSearchedAt ?? Carbon::now()->subDays(10),
            'created_at' => Carbon::now()->subDays(10), 'updated_at' => Carbon::now()->subDays(10),
        ];
    }

    /** Junta pedaços de dados do prepareDatabase. */
    protected function merge(array ...$parts): array
    {
        return array_merge_recursive(...$parts);
    }

    protected function runSearches(?Carbon $now = null): int
    {
        return $this->app()->getContainer()->make(SavedSearchRunner::class)->runDue($now);
    }

    /** @return list<array<string, mixed>> */
    protected function notificationsFor(int $userId): array
    {
        return Notification::query()
            ->where('user_id', $userId)
            ->where('type', 'savedSearch')
            ->where('is_deleted', false)
            ->orderBy('id')
            ->get()
            ->map(fn (Notification $n) => ['subject_id' => $n->subject_id, 'data' => $n->data])
            ->all();
    }

    protected function search(int $id): ?SavedSearch
    {
        return SavedSearch::query()->find($id);
    }

    /** @return array<string, mixed> */
    protected function json(ResponseInterface $response): array
    {
        return (array) json_decode((string) $response->getBody(), true);
    }

    protected function isMysql(): bool
    {
        return in_array($this->database()->getDriverName(), ['mysql', 'mariadb'], true);
    }
}
