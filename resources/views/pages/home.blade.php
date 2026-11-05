@extends('layouts.app')

@section('title', 'Rooted here. Growing together.')
@section('description', 'Meet the independent businesses shaping Argyle and discover how membership helps them grow.')

@section('content')
    <section class="relative isolate overflow-hidden bg-[var(--rr-accent-light)]">
        <div class="absolute -right-24 -top-32 -z-10 size-[30rem] rounded-full border-[1px] border-[var(--rr-primary)]/10 sm:right-0" aria-hidden="true"></div>
        <div class="absolute right-12 top-16 -z-10 size-72 rounded-full border border-[var(--rr-primary)]/10" aria-hidden="true"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-[1.1fr_0.9fr]">
            <div class="max-w-2xl">
                <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-[var(--rr-primary)]/15 bg-white/60 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">
                    <span class="size-2 rounded-full bg-[var(--rr-highlight)]" aria-hidden="true"></span>
                    A local business growth initiative
                </p>
                <h1 class="text-5xl font-semibold leading-[1.04] tracking-[-0.055em] text-[var(--rr-neutral-dark)] sm:text-6xl">Support Argyle.<br><span class="font-serif font-normal italic text-[var(--rr-highlight)]">Grow your business.</span></h1>
                <p class="mt-7 max-w-xl text-lg leading-8 text-[var(--rr-neutral-mid)]">Join Argyle's local business community and connect with neighbors, fellow owners, and the people who make this place home.</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <a href="{{ route('register') }}" class="min-h-11 rounded-lg bg-[var(--rr-highlight)] px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">Join now <span aria-hidden="true">→</span></a>
                    <a href="{{ route('businesses.index') }}" class="min-h-11 rounded-lg border border-[var(--rr-primary)]/25 bg-white/70 px-6 py-3.5 text-sm font-semibold text-[var(--rr-primary)] transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">Explore local businesses</a>
                </div>
                <p class="mt-8 text-xs font-semibold uppercase tracking-[0.15em] text-[var(--rr-neutral-mid)]">Independent spirit. Shared momentum.</p>
            </div>
            <div class="relative mx-auto w-full max-w-lg">
                <div class="aspect-[4/3] overflow-hidden rounded-[2rem] bg-[var(--rr-accent-light)] p-5 shadow-xl shadow-[var(--rr-primary)]/10 sm:p-8">
                    <div class="relative flex h-full items-end overflow-hidden rounded-[1.4rem]">
                        <!-- Image from Laravel Storage -->
                        <img src="{{ asset('storage/img/Hero Section.jpg') }}" alt="Local landscape and community" class="absolute inset-0 h-full w-full object-cover">
                        
                        
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">How it works</p>
                <h2 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">A simple way to grow <span class="font-serif font-normal italic text-[var(--rr-accent)]">together.</span></h2>
            </div>
            <ol class="relative mt-12 grid gap-5 md:grid-cols-3">
                @foreach ([['01', 'Connect with us', 'Tell us about your local business and what you would like to grow.'], ['02', 'Get discovered', 'Build your place in a directory made for Argyle neighbors who want to shop local.'], ['03', 'Grow your community', 'Meet fellow businesses and make it easier for more neighbors to find you.']] as [$number, $title, $copy])
                    <li class="relative rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] p-7 shadow-sm">
                        <span class="flex size-11 items-center justify-center rounded-full bg-[var(--rr-accent-light)] text-sm font-bold text-[var(--rr-accent)]">{{ $number }}</span>
                        <h3 class="mt-5 text-xl font-semibold tracking-tight text-[var(--rr-neutral-dark)]">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $copy }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24">
        <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr]">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">More than a directory</p>
                <h2 class="mt-4 max-w-md text-4xl font-semibold leading-tight tracking-[-0.04em] sm:text-5xl">When local businesses grow, <span class="font-serif font-normal italic text-[var(--rr-accent)]">we all do.</span></h2>
            </div>
            <div>
                <p class="max-w-2xl text-lg leading-8 text-[var(--rr-neutral-mid)]">The Argyle Buzz brings independent businesses and neighbors closer. Together, we make it easier to find local favorites, share what makes them special, and keep more opportunity right here in Argyle.</p>
                <a href="{{ route('benefits') }}" class="mt-6 inline-flex min-h-11 items-center gap-2 rounded-sm text-sm font-bold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] decoration-2 underline-offset-4 hover:text-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]">Discover member benefits <span aria-hidden="true">→</span></a>
            </div>
        </div>
        <div class="mt-14 grid gap-4 sm:grid-cols-3">
            @foreach ([['01', 'Be found', 'Give local neighbors another way to discover the work and services you offer.'], ['02', 'Find your people', 'Build relationships with fellow owners, makers, and community partners.'], ['03', 'Grow with purpose', 'Be part of a more connected and resilient local economy.']] as [$number, $title, $copy])
                <article class="rounded-3xl border border-[var(--rr-border)] bg-white/65 p-7">
                    <span class="text-xs font-bold tracking-[0.16em] text-[var(--rr-highlight)]">{{ $number }}</span>
                    <h3 class="mt-8 text-xl font-semibold tracking-tight">{{ $title }}</h3>
                    <p class="mt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $copy }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="bg-[var(--rr-neutral-light)]">
        <div class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">Meet your neighbors</p>
                    <h2 class="mt-3 text-4xl font-semibold tracking-[-0.04em]">Made here, by us.</h2>
                </div>
                <a href="{{ route('businesses.index') }}" class="w-fit rounded-sm text-sm font-bold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] decoration-2 underline-offset-4 hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]">See all local businesses <span aria-hidden="true">→</span></a>
            </div>
            <div class="mt-9 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($featuredBusinesses as $business)
                    <article class="flex min-h-56 flex-col rounded-3xl bg-[var(--rr-background)] p-6 transition hover:-translate-y-1 hover:shadow-lg hover:shadow-[var(--rr-primary)]/5">
                        <div class="flex items-start justify-between gap-4">
                            <span class="flex size-12 items-center justify-center rounded-2xl bg-[var(--rr-accent-light)] font-serif text-lg font-semibold text-[var(--rr-accent)]">{{ \Illuminate\Support\Str::substr($business['name'], 0, 1) }}</span>
                            <span class="rounded-full bg-[var(--rr-accent-light)]/60 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.12em] text-[var(--rr-neutral-dark)]">Featured</span>
                        </div>
                        <p class="mt-5 text-xs font-semibold uppercase tracking-[0.12em] text-[var(--rr-highlight)]">{{ $business['category'] }}</p>
                        <h3 class="mt-2 text-xl font-semibold tracking-tight">{{ $business['name'] }}</h3>
                        <p class="mt-2 line-clamp-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $business['description'] }}</p>
                        <a href="{{ route('businesses.show', $business['slug']) }}" class="mt-auto inline-flex w-fit rounded-sm pt-5 text-sm font-bold text-[var(--rr-primary)] hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">Meet the business <span class="ml-2" aria-hidden="true">→</span></a>
                    </article>
                @empty
                    <p class="rounded-3xl bg-[var(--rr-background)] p-7 text-[var(--rr-neutral-mid)] sm:col-span-2 lg:col-span-3">Our featured neighbors are coming soon. Explore the directory to discover local businesses.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="bg-[var(--rr-neutral-light)]">
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[0.9fr_1.1fr]">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">Membership</p>
                <h2 class="mt-3 text-4xl font-semibold tracking-[-0.04em]">Local is worth investing in.</h2>
                <p class="mt-4 max-w-xl leading-7 text-[var(--rr-neutral-mid)]">Be part of a connected community of independent businesses working toward a stronger Argyle.</p>
            </div>
            <div class="rounded-xl border border-[var(--rr-border)] bg-white p-7 shadow-sm sm:p-9">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">The Argyle Buzz</p>
                        <h3 class="mt-2 text-2xl font-semibold">Business membership</h3>
                    </div>
                    <span class="rounded-full bg-[var(--rr-accent-light)] px-3 py-1.5 text-xs font-semibold text-[var(--rr-accent)]">Community-led</span>
                </div>
                <p class="mt-6 text-3xl font-bold tracking-tight text-[var(--rr-primary)]">{{ $membership['price_label'] }}</p>
                @if (!is_numeric($membership['amount']))
                    <p class="mt-2 text-sm text-[var(--rr-neutral-mid)]">Pricing is being finalized. Contact us to learn about joining.</p>
                @else
                    <p class="mt-2 text-sm text-[var(--rr-neutral-mid)]">Membership contribution, billed {{ $membership['period'] }}.</p>
                @endif
                <a href="{{ route('pricing') }}" class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-[var(--rr-highlight)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">Explore membership <span class="ml-2" aria-hidden="true">→</span></a>
            </div>
        </div>
    </section>

    <section id="faq" class="mx-auto max-w-4xl scroll-mt-24 px-5 py-16 sm:px-8 sm:py-20">
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">Frequently asked questions</p>
            <h2 class="mt-3 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">A few things you might be wondering.</h2>
        </div>
        <div class="mt-9 divide-y divide-[var(--rr-border)] border-y border-[var(--rr-border)]">
            <details class="group py-5">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 text-left font-semibold text-[var(--rr-neutral-dark)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">Who can join The Argyle Buzz?<span class="text-xl text-[var(--rr-accent)] transition group-open:rotate-45" aria-hidden="true">+</span></summary>
                <p class="max-w-3xl pt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">The initiative is for local businesses and community members who want to help independent businesses thrive in Argyle. Contact us if you are unsure whether your business is a fit.</p>
            </details>
            <details class="group py-5">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 text-left font-semibold text-[var(--rr-neutral-dark)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">What does membership include?<span class="text-xl text-[var(--rr-accent)] transition group-open:rotate-45" aria-hidden="true">+</span></summary>
                <p class="max-w-3xl pt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">Membership is designed to help local businesses get discovered and build connections with other owners and the broader community. See the <a href="{{ route('benefits') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4">benefits page</a> for more details.</p>
            </details>
            <details class="group py-5">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 text-left font-semibold text-[var(--rr-neutral-dark)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">How much does membership cost?<span class="text-xl text-[var(--rr-accent)] transition group-open:rotate-45" aria-hidden="true">+</span></summary>
                <p class="max-w-3xl pt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">@if (is_numeric($membership['amount'])) Membership is {{ $membership['price_label'] }}. @else Membership pricing is still being finalized. Visit the <a href="{{ route('pricing') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4">pricing page</a> for updates or see the contact page for current placeholder details. @endif</p>
            </details>
            <details class="group py-5">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 text-left font-semibold text-[var(--rr-neutral-dark)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">How can neighbors support local businesses?<span class="text-xl text-[var(--rr-accent)] transition group-open:rotate-45" aria-hidden="true">+</span></summary>
                <p class="max-w-3xl pt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">Start by exploring the directory, visiting a local business, and sharing your favorite places with friends and neighbors.</p>
            </details>
            <details class="group py-5">
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 text-left font-semibold text-[var(--rr-neutral-dark)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">Can I suggest a business for the directory?<span class="text-xl text-[var(--rr-accent)] transition group-open:rotate-45" aria-hidden="true">+</span></summary>
                <p class="max-w-3xl pt-3 text-sm leading-6 text-[var(--rr-neutral-mid)]">The directory features local businesses with published profiles. Contact us if you would like to suggest a business for the initiative.</p>
            </details>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-24">
        <div class="overflow-hidden rounded-[2rem] bg-[var(--rr-primary)] px-7 py-12 text-[var(--rr-background)] sm:px-12 sm:py-16">
            <div class="grid items-center gap-8 md:grid-cols-[1fr_auto]">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-accent-light)]">Argyle is better together</p>
                    <h2 class="mt-4 max-w-2xl text-4xl font-semibold leading-tight tracking-[-0.04em] sm:text-5xl">Your next chapter can start right here.</h2>
                    <p class="mt-4 max-w-xl leading-7 text-[var(--rr-accent-light)]">Join a community that believes Argyle's future is built by the businesses already calling it home.</p>
                </div>
                <a href="{{ route('register') }}" class="w-fit rounded-full bg-[var(--rr-accent-light)] px-6 py-3.5 text-sm font-bold text-[var(--rr-neutral-dark)] transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-accent-light)]">Join the community <span aria-hidden="true">→</span></a>
            </div>
        </div>
    </section>
@endsection
