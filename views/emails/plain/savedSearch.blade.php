<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('ramon-saved-searches.email.plain.body', [
'{query}' => $blueprint->search->query,
'{count}' => $blueprint->count,
'{title}' => $blueprint->post->discussion->title,
'{discussion_url}' => $url->to('forum')->route('discussion', ['id' => $blueprint->post->discussion_id, 'near' => $blueprint->post->number]),
'{search_url}' => $url->to('forum')->route('index').'?'.http_build_query(['q' => $blueprint->search->query]),
]) !!}
</x-slot:body>
</x-mail::plain.notification>
