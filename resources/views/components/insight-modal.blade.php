<div
    x-data="{ insight: null }"
    x-on:open-insight.window="insight = $event.detail; $nextTick(() => $refs.close?.focus())"
    x-on:keydown.escape.window="insight = null"
    x-on:livewire:navigating.window="insight = null"
    x-effect="document.body.classList.toggle('overflow-hidden', insight !== null)"
>
    <div
        x-cloak
        x-show="insight"
        x-transition.opacity
        class="insight-overlay"
        role="dialog"
        aria-modal="true"
        :aria-label="insight?.title ?? 'Razlaga'"
    >
        <div class="insight-backdrop" x-on:click="insight = null"></div>

        <div class="insight-panel app-card">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-script text-brand">razlaga</p>
                    <h3 class="mt-2 text-2xl font-semibold tracking-tight text-ink" x-text="insight?.title"></h3>
                </div>
                <button type="button" class="btn-ghost shrink-0" x-ref="close" x-on:click="insight = null">Zapri</button>
            </div>

            <div class="mt-5 space-y-5" x-show="insight?.explanation || insight?.example">
                <div x-show="insight?.explanation">
                    <p class="font-script text-brand">daljša razlaga</p>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-ink/85" x-text="insight?.explanation"></p>
                </div>
                <div x-show="insight?.example">
                    <p class="font-script text-brand">konkreten primer</p>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-ink/85" x-text="insight?.example"></p>
                </div>
            </div>

            <p class="mt-5 text-sm leading-6 text-muted" x-show="insight && !insight.explanation && !insight.example">
                Daljša razlaga in konkreten primer bosta na voljo po ponovni obdelavi z AI.
            </p>
        </div>
    </div>
</div>
