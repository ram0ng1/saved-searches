<?php

/*
 * This file is part of ramon/saved-searches.
 *
 * Copyright (c) 2026 Ramon Guilherme.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ramon\SavedSearches\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\Post\Post;
use Flarum\User\User;
use Ramon\SavedSearches\SavedSearch;

/**
 * Uma notificação por busca salva e rodada: aponta para o resultado mais
 * novo e diz quantas discussões têm novidade.
 *
 * O id da busca vai nos dados porque o NotificationSyncer reconhece uma
 * notificação por tipo, assunto, remetente e dados: sem ele, duas pessoas
 * com a mesma busca apagariam a notificação uma da outra.
 */
class SavedSearchBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(
        public SavedSearch $search,
        public Post $post,
        public int $count
    ) {
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->post->discussion;
    }

    public function getFromUser(): ?User
    {
        return $this->post->user;
    }

    public function getData(): array
    {
        return [
            'searchId' => $this->search->id,
            'query' => $this->search->query,
            'count' => $this->count,
            'postNumber' => (int) $this->post->number,
        ];
    }

    public function getEmailViews(): array
    {
        return [
            'text' => 'ramon-saved-searches::emails.plain.savedSearch',
            'html' => 'ramon-saved-searches::emails.html.savedSearch',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ramon-saved-searches.email.subject', [
            '{query}' => $this->search->query,
            '{count}' => $this->count,
        ]);
    }

    public static function getType(): string
    {
        return 'savedSearch';
    }

    public static function getSubjectModel(): string
    {
        return Discussion::class;
    }
}
