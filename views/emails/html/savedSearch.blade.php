<x-mail::html.notification>
    <x-slot:body>
        {{-- O core dá às views de e-mail o MailTranslator e o MailFormatter: os valores passam pelo markdown como marcadores e voltam escapados (Flarum\Mail\SafeSubstitution). --}}
        {!! $formatter->convert($translator->trans('ramon-saved-searches.email.html.body', [
            '{query}' => $blueprint->search->query,
            '{count}' => $blueprint->count,
            '{title}' => $blueprint->post->discussion->title,
            '{discussion_url}' => $url->to('forum')->route('discussion', ['id' => $blueprint->post->discussion_id, 'near' => $blueprint->post->number]),
            '{search_url}' => $url->to('forum')->route('index').'?'.http_build_query(['q' => $blueprint->search->query]),
        ])) !!}
    </x-slot:body>

    <x-slot:preview>
        {!! $blueprint->post->formatContent() !!} {{-- HTML já renderizado pelo formatter do Flarum; nosemgrep: flarum-v2-blade-raw-output --}}
    </x-slot:preview>
</x-mail::html.notification>
