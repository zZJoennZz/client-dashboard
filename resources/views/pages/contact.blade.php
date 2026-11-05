@extends('layouts.app')

@section('title', 'Get in touch')
@section('description', 'Connect with The Argyle Buzz about membership, the local directory, and growing our local economy.')

@section('content')
    <section class="bg-[var(--rr-accent-light)]">
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--rr-highlight)]">We're all ears</p>
            <h1 class="mt-4 max-w-3xl text-5xl font-semibold tracking-[-0.05em] sm:text-7xl">Let's talk about <span class="font-serif font-normal italic text-[var(--rr-accent)]">local.</span></h1>
            <p class="mt-5 max-w-2xl text-lg leading-8 text-[var(--rr-neutral-mid)]">Whether you're a local business, a neighbor, or a future partner, we'd love to hear what's on your mind.</p>
        </div>
    </section>
    <section class="mx-auto grid max-w-7xl gap-12 px-5 py-14 sm:px-8 sm:py-20 lg:grid-cols-[0.8fr_1.2fr]">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.17em] text-[var(--rr-highlight)]">A neighborly hello</p>
            <h2 class="mt-3 text-3xl font-semibold tracking-tight">Start a conversation.</h2>
            <p class="mt-4 max-w-md leading-7 text-[var(--rr-neutral-mid)]">Have a question about membership or want to help more people discover a local business? Reach out and let's figure out what's possible together.</p>
            <dl class="mt-9 space-y-6">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--rr-neutral-mid)]">Email</dt>
                    <dd class="mt-1 text-sm text-[var(--rr-primary)]">[Contact email to be confirmed]</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--rr-neutral-mid)]">Based in</dt>
                    <dd class="mt-1 text-sm text-[var(--rr-primary)]">Argyle and the surrounding community</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-[0.14em] text-[var(--rr-neutral-mid)]">Response time</dt>
                    <dd class="mt-1 text-sm text-[var(--rr-primary)]">We'll share more details soon.</dd>
                </div>
            </dl>
        </div>
        <div class="rounded-[2rem] border border-[var(--rr-border)] bg-white/70 p-7 sm:p-10">
            <h2 class="text-2xl font-semibold tracking-tight">Send us a note</h2>
            <p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">This form is a preview. Message submission is not connected yet.</p>
            <form class="mt-7 space-y-5" aria-describedby="contact-form-note">
                <p id="contact-form-note" class="sr-only">This form is not currently available for submission.</p>
                <div>
                    <label for="name" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">Your name</label>
                    <input id="name" name="name" type="text" autocomplete="name" maxlength="120" required disabled class="w-full rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] placeholder:text-[var(--rr-neutral-mid)] focus:border-[var(--rr-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-accent)]/20 disabled:cursor-not-allowed disabled:opacity-70" placeholder="Jane Smith">
                </div>
                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" maxlength="255" required disabled class="w-full rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] placeholder:text-[var(--rr-neutral-mid)] focus:border-[var(--rr-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-accent)]/20 disabled:cursor-not-allowed disabled:opacity-70" placeholder="jane@example.com">
                </div>
                <div>
                    <label for="subject" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">What would you like to talk about?</label>
                    <select id="subject" name="subject" required disabled class="w-full rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] focus:border-[var(--rr-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-accent)]/20 disabled:cursor-not-allowed disabled:opacity-70">
                        <option value="">Choose a topic</option>
                        <option>Membership</option>
                        <option>Business directory</option>
                        <option>Community partnership</option>
                        <option>Something else</option>
                    </select>
                </div>
                <div>
                    <label for="message" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">Your message</label>
                    <textarea id="message" name="message" rows="5" minlength="10" maxlength="3000" required disabled class="w-full resize-y rounded-xl border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] placeholder:text-[var(--rr-neutral-mid)] focus:border-[var(--rr-accent)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-accent)]/20 disabled:cursor-not-allowed disabled:opacity-70" placeholder="Tell us a little about what brings you here..."></textarea>
                </div>
                <button type="button" disabled class="w-full cursor-not-allowed rounded-full bg-[var(--rr-highlight)] px-6 py-3.5 text-sm font-bold text-white opacity-60">Message form coming soon</button>
            </form>
        </div>
    </section>
@endsection
