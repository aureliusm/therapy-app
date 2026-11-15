@props(['text' => null])

@php($items = \App\Models\TherapySession::bulletItems($text))

@if ($items === [])
    <p class="text-sm text-ink/85">—</p>
@else
    <ul class="list-disc space-y-2 pl-5 text-sm leading-6 text-ink/85 marker:text-brand">
        @foreach ($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
@endif
