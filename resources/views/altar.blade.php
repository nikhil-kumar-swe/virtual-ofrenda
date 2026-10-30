@extends('layouts.ofrenda')

@section('title', 'Your ofrenda — Virtual Ofrenda')

@section('content')
<div id="altar-root" data-id="{{ $tribute->id }}" data-home="{{ url('/') }}">

    <h1 id="altar-title" class="mb-6 text-center font-serif text-3xl text-parchment sm:text-4xl">
        Your ofrenda
    </h1>

    {{-- Progress panel (shown while the tribute is being made) --}}
    <div id="status-panel" class="glow-card mb-6 flex flex-col items-center p-6 text-center" role="status" aria-live="polite">
        <div class="flex items-end gap-5">
            <div class="candle"><div class="flame"></div><div class="candle-body"></div></div>
            <div class="candle" style="margin-bottom:10px"><div class="flame" style="animation-delay:.4s"></div><div class="candle-body" style="height:56px"></div></div>
            <div class="candle"><div class="flame" style="animation-delay:.8s"></div><div class="candle-body"></div></div>
        </div>
        <p class="mt-6 font-serif text-xl text-amber">Lighting the candles…</p>
        <p id="status-text" class="mt-2 text-sm text-parchment/70">Waiting for the story to begin</p>
        <p id="status-hint" class="mt-3 hidden text-xs text-parchment/50"></p>
    </div>

    {{-- Failed panel --}}
    <div id="failed-panel" class="glow-card mb-6 hidden border-magenta/60 p-6 text-center" role="alert">
        <p class="font-serif text-xl text-magenta">The candles would not light</p>
        <p id="failed-msg" class="mt-3 text-sm text-parchment/80"></p>
        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <button type="button" id="retry-btn" class="btn-marigold hidden">Try again</button>
            <a href="{{ url('/') }}" class="btn-ghost">Back to start</a>
        </div>
    </div>

    {{-- The altar --}}
    <section aria-label="Ofrenda altar" class="mb-8">
        <div class="altar">
            <div class="altar-tier altar-tier-0" id="tier-0"></div>
            <div class="altar-tier altar-tier-1" id="tier-1"></div>
            <div class="altar-tier altar-tier-2" id="tier-2"></div>
        </div>
    </section>

    {{-- Story --}}
    <article id="story-card" class="glow-card mb-6 hidden p-5 sm:p-7">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-widest text-amber">Tribute</h2>
        <div id="story-body" class="space-y-4 font-serif text-lg leading-relaxed text-parchment"></div>
    </article>

    {{-- Poem --}}
    <article id="poem-card" class="glow-card mb-6 hidden p-5 sm:p-7">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-widest text-amber">Poem</h2>
        <p id="poem-body" class="whitespace-pre-line font-serif text-lg italic leading-relaxed text-parchment"></p>
    </article>

    <div id="done-actions" class="hidden text-center">
        <a href="{{ url('/') }}" class="btn-ghost">Remember someone else</a>
    </div>
</div>
@endsection