@extends('layouts.app')

@section('title', 'Membership')
@section('description', 'Explore Rediscover Roanoke membership and join a community committed to local business growth.')

@section('content')
    <section class="bg-[var(--rr-accent-light)]">
        <div class="mx-auto max-w-7xl px-5 py-16 text-center sm:px-8 sm:py-20">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">A place at the local table</p>
            <h1 class="mx-auto mt-4 max-w-3xl text-5xl font-semibold tracking-[-0.05em] sm:text-6xl">Invest in a more <span class="font-serif font-normal italic text-[var(--rr-accent)]">local</span> future.</h1>
            <p class="mx-auto mt-5 max-w-2xl text-lg leading-8 text-[var(--rr-neutral-mid)]">Membership brings your business into a community that believes Roanoke grows stronger when local businesses grow.</p>
        </div>
    </section>
    <section class="mx-auto max-w-5xl px-5 py-14 sm:px-8 sm:py-20">
        <article class="mx-auto max-w-2xl overflow-hidden rounded-[2rem] border border-[var(--rr-border)] bg-white shadow-xl shadow-[var(--rr-primary)]/5">
            <div class="bg-[var(--rr-primary)] px-8 py-8 text-white sm:px-10">
                <p class="text-xs font-bold uppercase tracking-[0.17em] text-[var(--rr-accent-light)]">Rediscover Roanoke membership</p>
                <h2 class="mt-3 text-3xl font-semibold tracking-tight">Rooted in community.</h2>
                <p class="mt-2 text-sm text-[var(--rr-accent-light)]">For independent businesses ready to grow together.</p>
            </div>
            <div class="p-8 sm:p-10">
                <p class="text-4xl font-semibold tracking-tight text-[var(--rr-neutral-dark)]">{{ $membership['price_label'] }}</p>
                @if (!is_numeric($membership['amount']))
                    <p class="mt-3 inline-flex rounded-full bg-[var(--rr-accent-light)]/50 px-3 py-1.5 text-xs font-semibold text-[var(--rr-neutral-dark)]">Membership pricing is a placeholder and has not yet been confirmed.</p>
                @else
                    <p class="mt-2 text-sm text-[var(--rr-neutral-mid)]">Membership contribution, billed {{ $membership['period'] }}.</p>
                @endif
                <ul class="mt-8 space-y-4 text-sm leading-6 text-[var(--rr-neutral-mid)]">
                    @foreach (['A business profile in the local directory', 'A place in a connected community of local owners', 'More ways for Roanoke neighbors to discover your business', 'A shared voice for local economic growth'] as $benefit)
                        <li class="flex gap-3"><span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-[var(--rr-accent-light)] text-xs font-bold text-[var(--rr-accent)]" aria-hidden="true">✓</span>{{ $benefit }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('register') }}" class="mt-9 flex min-h-11 w-full items-center justify-center rounded-lg bg-[var(--rr-highlight)] px-6 py-3.5 text-sm font-bold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">Join now <span class="ml-2" aria-hidden="true">→</span></a>
            </div>
        </article>
        <p class="mx-auto mt-10 max-w-xl text-center text-sm leading-6 text-[var(--rr-neutral-mid)]">Questions about membership? <a href="{{ route('contact') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4 hover:text-[var(--rr-highlight)]">We'd love to hear from you.</a></p>
    </section>
@endsection
