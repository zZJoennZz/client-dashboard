@extends('layouts.app')

@section('title', 'Local business directory')
@section('description', 'Find independent businesses, makers, and community favorites in Argyle.')
@section('canonical', route('businesses.index'))

@section('content')
    <section class="bg-[var(--rr-accent-light)]">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">Shop close to home</p>
            <h1 class="mt-4 text-5xl font-semibold tracking-[-0.05em] sm:text-6xl">Find your <span class="font-serif font-normal italic text-[var(--rr-accent)]">local.</span></h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-[var(--rr-neutral-mid)]">Meet the independent businesses, neighborhood favorites, and creative people making Argyle feel like home.</p>
        </div>
    </section>
    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
        <form action="{{ route('businesses.index') }}" method="GET" class="mb-9 grid gap-4 rounded-xl border border-[var(--rr-border)] bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
            <label class="block lg:col-span-2"><span class="mb-2 block text-sm font-semibold">Search local businesses</span>
                <input name="q" type="search" value="{{ $filters['query'] }}" maxlength="100" placeholder="Business, category, or neighborhood" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-2.5 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
            </label>
            <label class="block"><span class="mb-2 block text-sm font-semibold">Category</span>
                <select name="category" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-2.5 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block"><span class="mb-2 block text-sm font-semibold">City</span>
                <select name="location" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-2.5 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                    <option value="">All locations</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location }}" @selected($filters['location'] === $location)>{{ $location }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block"><span class="mb-2 block text-sm font-semibold">Sort by</span>
                <select name="sort" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-2.5 text-sm">
                    <option value="featured" @selected($filters['sort'] === 'featured')>Featured</option>
                    <option value="name" @selected($filters['sort'] === 'name')>Name</option>
                    <option value="newest" @selected($filters['sort'] === 'newest')>Recently added</option>
                </select>
            </label>
            <div class="flex gap-2 lg:col-span-5">
                <button type="submit" class="min-h-11 rounded-lg bg-[var(--rr-primary)] px-5 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">Search</button>
                @if ($filters['query'] !== '' || $filters['category'] !== '' || $filters['location'] !== '' || $filters['sort'] !== 'featured')
                    <a href="{{ route('businesses.index') }}" class="inline-flex min-h-11 items-center rounded-lg border border-[var(--rr-border)] px-4 text-sm font-semibold text-[var(--rr-primary)] hover:bg-[var(--rr-neutral-light)]">Clear</a>
                @endif
            </div>
        </form>
        <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-highlight)]">The neighborhood is yours to explore</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight">{{ $businesses->total() }} {{ \Illuminate\Support\Str::plural('business', $businesses->total()) }} {{ $filters['query'] !== '' || $filters['category'] !== '' || $filters['location'] !== '' ? 'found' : 'to discover' }}</h2>
            </div>
            <p class="text-sm text-[var(--rr-neutral-mid)]">A directory of local, independent businesses</p>
        </div>
        @forelse ($businesses as $business)
            @php($cover = $business->images->firstWhere('is_primary', true) ?? $business->images->first())
            <article class="group mb-4 grid gap-5 rounded-3xl border border-[var(--rr-border)] bg-white/70 p-5 transition hover:border-[var(--rr-highlight)]/30 hover:bg-white sm:grid-cols-[10rem_1fr_auto] sm:items-center sm:p-6">
                <a href="{{ route('businesses.show', $business->slug) }}" class="block aspect-[4/3] overflow-hidden rounded-2xl bg-[var(--rr-accent-light)]">
                    @if ($cover)
                        <img src="{{ route('business-media.images', $cover) }}" alt="{{ $cover->alt_text ?: $business->company_name.' listing photo' }}" loading="lazy" class="size-full object-cover">
                    @else
                        <span aria-hidden="true" class="flex size-full items-center justify-center font-serif text-5xl text-[var(--rr-accent)]">{{ \Illuminate\Support\Str::substr($business->company_name, 0, 1) }}</span>
                    @endif
                </a>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($business->categories as $category)
                            <span class="text-xs font-bold uppercase tracking-[0.12em] text-[var(--rr-highlight)]">{{ $category->name }}</span>
                        @endforeach
                        @if ($business->is_featured)
                            <span class="rounded-full bg-[var(--rr-accent-light)] px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.1em] text-[var(--rr-accent)]">Featured</span>
                        @endif
                    </div>
                    <h3 class="mt-1 text-xl font-semibold tracking-tight"><a href="{{ route('businesses.show', $business->slug) }}" class="hover:text-[var(--rr-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">{{ $business->company_name }}</a></h3>
                    @if ($business->tagline)<p class="mt-1 text-sm font-medium text-[var(--rr-neutral-dark)]">{{ $business->tagline }}</p>@endif
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $business->short_description ?: \Illuminate\Support\Str::limit(strip_tags($business->description ?? ''), 180) }}</p>
                    @if ($business->city || $business->state)<p class="mt-3 text-xs font-medium text-[var(--rr-neutral-mid)]"><span aria-hidden="true">⌖</span> {{ collect([$business->city, $business->state])->filter()->join(', ') }}</p>@endif
                </div>
                <div class="flex flex-wrap gap-3 sm:flex-col sm:items-end">
                    @if ($business->website)<a href="{{ $business->website }}" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-[var(--rr-primary)] underline">Visit website</a>@endif
                    @if ($business->contact_phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->contact_phone) }}" class="text-xs font-semibold text-[var(--rr-primary)] underline">Call</a>@endif
                    <a href="{{ route('businesses.show', $business->slug) }}" class="inline-flex min-h-10 items-center font-bold text-sm text-[var(--rr-primary)] group-hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4">View profile <span class="ml-2" aria-hidden="true">→</span></a>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-[var(--rr-border)] bg-white/70 px-6 py-16 text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-[var(--rr-accent-light)] text-2xl text-[var(--rr-accent)]" aria-hidden="true">✳</span>
                <h2 class="mt-5 text-xl font-semibold">{{ $filters['query'] !== '' || $filters['category'] !== '' || $filters['location'] !== '' ? 'No matches just yet.' : 'Your next local favorite is on the way.' }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $filters['query'] !== '' || $filters['category'] !== '' || $filters['location'] !== '' ? 'Try a different search or clear your filters to see all local businesses.' : "We don't have any businesses in the directory just yet. Check back soon as our local community grows." }}</p>
                @if ($filters['query'] !== '' || $filters['category'] !== '' || $filters['location'] !== '')
                    <a href="{{ route('businesses.index') }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg bg-[var(--rr-primary)] px-5 text-sm font-semibold text-white">Clear filters</a>
                @endif
            </div>
        @endforelse
        <div class="mt-8">{{ $businesses->links() }}</div>
        <p class="mt-12 text-center text-sm text-[var(--rr-neutral-mid)]">Know a business that belongs here? <a href="{{ route('contact') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4">Get in touch.</a></p>
    </section>
@endsection
