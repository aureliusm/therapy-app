@props(['items' => []])

<div x-data="{}">
    @if ($items === [])
        <p class="text-sm text-ink/85">—</p>
    @else
        <ul class="space-y-2 text-sm leading-6 text-ink/85">
            @foreach ($items as $item)
                <li>
                    <button
                        type="button"
                        data-insight-title="{{ $item['title'] }}"
                        class="flex w-full cursor-pointer items-start gap-2 text-left"
                        x-on:click="$dispatch('open-insight', {{ \Illuminate\Support\Js::from($item) }})"
                    >
                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                        <span class="underline decoration-brand/40 underline-offset-4 transition hover:text-mauve-dark hover:decoration-brand">{{ $item['title'] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
