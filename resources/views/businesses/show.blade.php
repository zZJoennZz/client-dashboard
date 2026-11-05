@extends('layouts.app')

@section('title', $business->seo_title)
@section('description', $business->seo_description)
@section('canonical', $business->canonical_url)
@section('og_type', 'business.business')
@if ($business->images->isNotEmpty())
    @section('social_image', route('business-media.images', $business->images->firstWhere('is_primary', true) ?? $business->images->first()))
@endif

@section('content')
    <section class="bg-[var(--rr-accent-light)]">
        <div class="mx-auto max-w-7xl px-5 py-7 sm:px-8">
            <a href="{{ route('businesses.index') }}" class="inline-flex items-center gap-2 rounded-sm text-sm font-semibold text-[var(--rr-accent)] hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]"><span aria-hidden="true">←</span> Back to directory</a>
        </div>
    </section>
    <section class="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-16">
        @php($cover = $business->images->firstWhere('is_primary', true) ?? $business->images->first())
        <article class="overflow-hidden rounded-[2rem] border border-[var(--rr-border)] bg-white">
            <div class="grid gap-1 bg-[var(--rr-accent-light)] md:grid-cols-2">
                @foreach ($business->images->take(4) as $image)
                    <a href="{{ route('business-media.images', $image) }}" target="_blank" rel="noopener noreferrer" class="block aspect-[16/10] overflow-hidden focus-visible:outline focus-visible:outline-4 focus-visible:outline-[var(--rr-highlight)]">
                        <img src="{{ route('business-media.images', $image) }}" alt="{{ $image->alt_text ?: $business->company_name.' photo '.($loop->iteration) }}" @if (!$loop->first) loading="lazy" @endif class="size-full object-cover">
                    </a>
                @endforeach
                @if ($business->images->isEmpty())
                    <div class="flex min-h-60 items-center justify-center md:col-span-2">
                        <span class="flex size-28 items-center justify-center rounded-[2rem] bg-[var(--rr-accent)] font-serif text-5xl text-[var(--rr-accent-light)]" aria-hidden="true">{{ \Illuminate\Support\Str::substr($business->company_name, 0, 1) }}</span>
                    </div>
                @endif
            </div>
            <div class="grid gap-10 p-7 sm:p-10 lg:grid-cols-[minmax(0,1fr)_20rem] lg:p-12">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        @foreach ($business->categories as $category)
                            <span class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--rr-highlight)]">{{ $category->name }}</span>
                        @endforeach
                        @if ($business->is_featured)
                            <span class="rounded-full bg-[var(--rr-accent-light)] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.1em] text-[var(--rr-accent)]">Featured member</span>
                        @endif
                    </div>
                    <h1 class="mt-4 text-4xl font-semibold tracking-[-0.04em] sm:text-6xl">{{ $business->company_name }}</h1>
                    @if ($business->tagline)<p class="mt-4 text-xl font-medium text-[var(--rr-neutral-dark)]">{{ $business->tagline }}</p>@endif
                    @if ($business->short_description)<p class="mt-4 max-w-3xl text-lg leading-8 text-[var(--rr-neutral-mid)]">{{ $business->short_description }}</p>@endif
                    @if ($business->description)<div class="mt-7 max-w-3xl whitespace-pre-line text-sm leading-7 text-[var(--rr-neutral-mid)]">{{ $business->description }}</div>@endif
                    @if ($business->specialties)
                        <section class="mt-8" aria-labelledby="specialties-heading">
                            <h2 id="specialties-heading" class="text-lg font-semibold">Specialties</h2>
                            <ul class="mt-3 flex flex-wrap gap-2">
                                @foreach ($business->specialties as $specialty)<li class="rounded-full bg-[var(--rr-accent-light)] px-3 py-1.5 text-xs font-medium text-[var(--rr-accent)]">{{ $specialty }}</li>@endforeach
                            </ul>
                        </section>
                    @endif
                    @if ($business->highlights->isNotEmpty())
                        <section class="mt-10" aria-labelledby="highlights-heading">
                            <h2 id="highlights-heading" class="text-2xl font-semibold">Services and highlights</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                @foreach ($business->highlights as $highlight)
                                    <article class="rounded-xl border border-[var(--rr-border)] p-5">
                                        <h3 class="font-semibold">{{ $highlight->name }}</h3>
                                        @if ($highlight->description)<p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $highlight->description }}</p>@endif
                                        @if ($highlight->price_information)<p class="mt-3 text-sm font-medium">{{ $highlight->price_information }}</p>@endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                    @if ($business->accessibility_information)
                        <section class="mt-8"><h2 class="text-lg font-semibold">Accessibility</h2><p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">{{ $business->accessibility_information }}</p></section>
                    @endif
                    @if ($business->service_areas)
                        <section class="mt-8"><h2 class="text-lg font-semibold">Service areas</h2><p class="mt-2 text-sm text-[var(--rr-neutral-mid)]">{{ implode(', ', $business->service_areas) }}</p></section>
                    @endif
                    @if ($business->video)
                        <section class="mt-10" aria-labelledby="video-heading">
                            <h2 id="video-heading" class="text-2xl font-semibold">Meet {{ $business->company_name }}</h2>
                            <div class="mt-4 aspect-video overflow-hidden rounded-2xl bg-black">
                                @if ($business->video_embed_url)
                                    <iframe src="{{ $business->video_embed_url }}" title="{{ $business->company_name }} video" class="size-full" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                                @elseif ($business->video_playback_url)
                                    <video controls preload="metadata" class="size-full"><source src="{{ $business->video_playback_url }}" type="{{ $business->video->source_type === 'upload' ? 'video/'.pathinfo($business->video->file_path, PATHINFO_EXTENSION) : 'video/'.pathinfo(parse_url($business->video_playback_url, PHP_URL_PATH), PATHINFO_EXTENSION) }}">Your browser does not support embedded video playback.</video>
                                    <p class="mt-2 text-xs text-[var(--rr-neutral-mid)]">Playback of external video links depends on the provider and browser-supported formats.</p>
                                @endif
                            </div>
                        </section>
                    @endif
                </div>

                <aside class="space-y-6">
                    <section class="rounded-2xl bg-[var(--rr-background)] p-5" aria-labelledby="contact-heading">
                        <h2 id="contact-heading" class="text-lg font-semibold">Contact</h2>
                        <div class="mt-4 space-y-3">
                            @if ($business->website)<a href="{{ $business->website }}" target="_blank" rel="noopener noreferrer" class="flex min-h-11 items-center justify-center rounded-lg bg-[var(--rr-primary)] px-4 text-sm font-semibold text-white hover:bg-[var(--rr-primary-light)]">Visit website <span class="ml-2" aria-hidden="true">↗</span></a>@endif
                            @if ($business->contact_phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->contact_phone) }}" class="block text-sm font-medium text-[var(--rr-primary)] underline">Call {{ $business->contact_phone }}</a>@endif
                            @if ($business->alternate_phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->alternate_phone) }}" class="block text-sm font-medium text-[var(--rr-primary)] underline">Alternate: {{ $business->alternate_phone }}</a>@endif
                            @if ($business->contact_email)<a href="mailto:{{ $business->contact_email }}" class="block break-all text-sm font-medium text-[var(--rr-primary)] underline">Email {{ $business->contact_email }}</a>@endif
                            @if ($business->contact_name)<p class="text-xs text-[var(--rr-neutral-mid)]">Contact: {{ $business->contact_name }}</p>@endif
                        </div>
                        @if ($business->formatted_address)
                            <address class="mt-5 whitespace-pre-line text-sm not-italic leading-6 text-[var(--rr-neutral-mid)]">{{ collect([$business->address_line_1, $business->address_line_2, $business->city, $business->state, $business->postal_code, $business->country])->filter()->join(', ') }}</address>
                            <a href="{{ $business->google_maps_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-10 items-center text-sm font-semibold text-[var(--rr-primary)] underline">View on Google Maps ↗</a>
                        @endif
                    </section>
                    @if ($business->hours->isNotEmpty())
                        <section class="rounded-2xl bg-[var(--rr-background)] p-5" aria-labelledby="hours-heading">
                            <h2 id="hours-heading" class="text-lg font-semibold">Business hours</h2>
                            <dl class="mt-4 space-y-2 text-sm">
                                @foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $dayIndex => $day)
                                    @php($hours = $business->hours->firstWhere('day_of_week', $dayIndex))
                                    @if ($hours)
                                        <div class="flex justify-between gap-3"><dt>{{ $day }}</dt><dd class="text-right text-[var(--rr-neutral-mid)]">{{ $hours->by_appointment ? 'By appointment' : ($hours->is_closed ? 'Closed' : (substr($hours->opens_at, 0, 5).' – '.substr($hours->closes_at, 0, 5))) }}</dd></div>
                                    @endif
                                @endforeach
                            </dl>
                        </section>
                    @endif
                    <section class="rounded-2xl bg-[var(--rr-background)] p-5" aria-labelledby="social-heading">
                        <h2 id="social-heading" class="text-lg font-semibold">Connect</h2>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach (['Facebook' => 'facebook_url', 'Instagram' => 'instagram_url', 'LinkedIn' => 'linkedin_url', 'TikTok' => 'tiktok_url', 'YouTube' => 'youtube_url', 'More social' => 'other_social_url'] as $label => $field)
                                @if ($business->{$field})<a class="text-sm font-medium text-[var(--rr-primary)] underline" href="{{ $business->{$field} }}" target="_blank" rel="noopener noreferrer">{{ $label }} ↗</a>@endif
                            @endforeach
                            @if (!$business->facebook_url && !$business->instagram_url && !$business->linkedin_url && !$business->tiktok_url && !$business->youtube_url && !$business->other_social_url)<span class="text-sm text-[var(--rr-neutral-mid)]">Social profiles not provided.</span>@endif
                        </div>
                    </section>
                </aside>
            </div>
        </article>
        <p class="mt-10 rounded-2xl bg-[var(--rr-neutral-light)] p-6 text-sm leading-6 text-[var(--rr-neutral-mid)]">The Argyle Buzz celebrates the people and independent businesses that make our community. <a href="{{ route('businesses.index') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4">Explore more local businesses.</a></p>
    </section>
@endsection
