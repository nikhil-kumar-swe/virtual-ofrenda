@extends('layouts.ofrenda')

@section('title', 'Remember someone — Virtual Ofrenda')

@section('content')
    <section class="mb-6 text-center">
        <h1 class="font-serif text-3xl leading-tight text-parchment sm:text-4xl">
            Light a candle for someone you miss
        </h1>
        <p class="mt-3 text-parchment/70">
            Share a few memories. We'll turn them into a tribute story, a short poem,
            and a candlelit altar, using only what you tell us.
        </p>
    </section>

    <form id="memory-form" class="glow-card space-y-5 p-5 sm:p-6" novalidate>
        <input type="hidden" id="source" value="typed">

        <div>
            <label for="name" class="mb-1.5 block text-sm font-medium text-amber">
                Who are we remembering?
            </label>
            <input id="name" type="text" maxlength="80" autocomplete="off"
                   class="field" placeholder="e.g. Abuela Rosa">
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="memory-text" class="block text-sm font-medium text-amber">
                    A memory, in your own words
                </label>
                <button type="button" id="mic-btn" class="btn-ghost !px-3 !py-1.5 text-sm">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <rect x="9" y="3" width="6" height="12" rx="3"/>
                        <path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>
                    </svg>
                    <span id="mic-label">Speak</span>
                </button>
            </div>

            <textarea id="memory-text" rows="7" maxlength="4000"
                      class="field" placeholder="She always hummed while making tamales..."></textarea>

            <p id="mic-status" class="mt-2 hidden text-sm text-teal" role="status" aria-live="polite"></p>
            <p class="mt-2 text-xs text-parchment/50">
                Voice works best for short clips (about 15 seconds). Typing is always available.
            </p>
        </div>

        <p id="error-box" class="hidden rounded-xl border border-magenta/60 bg-magenta/10 px-4 py-3 text-sm text-parchment" role="alert"></p>

        <button type="submit" id="submit-btn" class="btn-marigold w-full text-lg">
            Create the ofrenda
        </button>
    </form>

    {{-- Loader overlay --}}
    <div id="loader" class="fixed inset-0 z-50 hidden flex-col items-center justify-center bg-night/95 px-6 text-center" role="status" aria-live="polite">
        <div class="flex items-end gap-6">
            <div class="candle"><div class="flame"></div><div class="candle-body"></div></div>
            <div class="candle" style="margin-bottom:12px"><div class="flame" style="animation-delay:.4s"></div><div class="candle-body" style="height:56px"></div></div>
            <div class="candle"><div class="flame" style="animation-delay:.8s"></div><div class="candle-body"></div></div>
        </div>
        <p class="mt-8 font-serif text-2xl text-amber">Lighting the candles…</p>
        <p id="loader-step" class="mt-2 text-sm text-parchment/60">Saving your memory</p>
    </div>
@endsection