@extends('layouts.app')

@section('title', 'Benefits for local businesses')
@section('description', 'Discover how The Argyle Buzz membership helps local businesses build visibility, connection, and momentum.')

@section('content')
    <section class="bg-[var(--rr-accent-light)]">
        <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-28">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">A little more local love</p>
            <h1 class="mt-5 max-w-3xl text-5xl font-semibold leading-[1.06] tracking-[-0.05em] sm:text-6xl">Good things happen when we <span class="font-serif font-normal italic text-[var(--rr-accent)]">show up</span> for each other.</h1>
            <p class="mt-7 max-w-2xl text-lg leading-8 text-[var(--rr-neutral-mid)]">Membership connects your business with neighbors, fellow local owners, and a community that wants to see Argyle thrive.</p>
            <a href="{{ route('pricing') }}" class="mt-9 inline-flex rounded-full bg-[var(--rr-highlight)] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">Find your membership <span class="ml-2" aria-hidden="true">→</span></a>
        </div>
    </section>
    <section id="how-it-works" class="mx-auto max-w-7xl scroll-mt-24 px-5 py-16 sm:px-8 sm:py-20">
        <div class="max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">How it works</p>
            <h2 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Three steps toward a more connected local economy.</h2>
        </div>
        <ol class="mt-9 grid gap-4 md:grid-cols-3">
            @foreach ([['01', 'Start a conversation', 'Tell us about your business and what you hope to get from the community.'], ['02', 'Create your profile', 'Share the details that help Argyle neighbors discover what you offer.'], ['03', 'Grow together', 'Connect with local owners and the people who care about this place.']] as [$number, $title, $copy])
                <li class="rounded-xl border border-[var(--rr-border)] bg-white p-7 shadow-sm">
                    <span class="flex size-11 items-center justify-center rounded-full bg-[var(--rr-accent-light)] text-sm font-bold text-[var(--rr-accent)]">{{ $number }}</span>
                    <h3 class="mt-5 text-xl font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $copy }}</p>
                </li>
            @endforeach
        </ol>
    </section>
    <section class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
        <div class="max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">Made for the people who make this place</p>
            <h2 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Local business. Local advantage.</h2>
        </div>
        <div class="mt-12 grid gap-4 md:grid-cols-2">
            @foreach ([['01', 'Get discovered', 'Give curious neighbors a new way to find your business with a dedicated profile in our local directory.'], ['02', 'Build real connections', 'Meet other independent businesses and community members who share your commitment to Argyle.'], ['03', 'Share your story', 'Highlight what makes your business special and help more people choose local.'], ['04', 'Grow the local economy', 'Be part of a community creating more opportunity and keeping local dollars close to home.']] as [$number, $title, $copy])
                <article class="rounded-xl border border-[var(--rr-border)] bg-white/70 p-7 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-md sm:p-9">
                    <span class="flex size-11 items-center justify-center rounded-full bg-[var(--rr-accent-light)] text-xs font-bold text-[var(--rr-accent)]">{{ $number }}</span>
                    <h3 class="mt-6 text-2xl font-semibold tracking-tight">{{ $title }}</h3>
                    <p class="mt-3 max-w-lg leading-7 text-[var(--rr-neutral-mid)]">{{ $copy }}</p>
                </article>
            @endforeach
        </div>
    </section>
    <section class="bg-[var(--rr-neutral-light)] px-5 py-16 sm:px-8 sm:py-20">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-7 rounded-[2rem] bg-[var(--rr-primary)] p-8 text-white sm:p-12 md:flex-row md:items-center">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-accent-light)]">Ready when you are</p>
                <h2 class="mt-3 max-w-2xl text-3xl font-semibold tracking-tight sm:text-4xl">Let's make more room for local to grow.</h2>
            </div>
            <a href="{{ route('register') }}" class="shrink-0 rounded-full bg-[var(--rr-accent-light)] px-6 py-3.5 text-sm font-bold text-[var(--rr-neutral-dark)] transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-accent-light)]">Join The Argyle Buzz <span aria-hidden="true">→</span></a>
        </div>
    </section>
@endsection
