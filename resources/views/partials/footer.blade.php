<footer class="bg-[var(--rr-primary)] text-white">
    <div class="mx-auto grid max-w-7xl gap-10 px-5 py-12 sm:px-8 sm:py-14 md:grid-cols-2 lg:grid-cols-4">
        <div>
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center gap-3 rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-warning)]">
                <img src="{{ asset('storage/branding/the-argyle-buzz-logo.png') }}" alt="The Argyle Buzz" width="475" height="88" class="h-10 w-auto rounded bg-white px-1">
            </a>
            <p class="mt-4 max-w-md text-sm leading-6 text-[var(--rr-border)]">A stronger local economy starts with knowing, choosing, and championing the people who make this place home.</p>
        </div>
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-warning)]">Explore</h2>
            <ul class="mt-4 space-y-2 text-sm text-[var(--rr-border)]">
                <li><a class="inline-flex min-h-11 items-center rounded-sm hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]" href="{{ route('businesses.index') }}">Business directory</a></li>
                <li><a class="inline-flex min-h-11 items-center rounded-sm hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]" href="{{ route('benefits') }}">How it works & benefits</a></li>
                <li><a class="inline-flex min-h-11 items-center rounded-sm hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]" href="{{ route('pricing') }}">Membership pricing</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-warning)]">Get involved</h2>
            <ul class="mt-4 space-y-2 text-sm text-[var(--rr-border)]">
                <li><a class="inline-flex min-h-11 items-center rounded-sm hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]" href="{{ route('register') }}">Join the community</a></li>
                <li><a class="inline-flex min-h-11 items-center rounded-sm hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]" href="{{ route('contact') }}">Contact us</a></li>
                <li><a class="inline-flex min-h-11 items-center rounded-sm hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]" href="{{ route('login') }}">Member login</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-warning)]">Serving Argyle</h2>
            <p class="mt-4 text-sm leading-6 text-[var(--rr-border)]">Rooted in Argyle and growing with our local business community.</p>
            <a class="mt-2 inline-flex min-h-11 items-center rounded-sm text-sm font-semibold text-white underline decoration-[var(--rr-warning)] underline-offset-4 hover:text-[var(--rr-warning)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-warning)]" href="{{ route('contact') }}">Get in touch</a>
        </div>
    </div>
    <div class="border-t border-white/15">
        <p class="mx-auto max-w-7xl px-5 py-5 text-xs text-[var(--rr-border)] sm:px-8">© {{ date('Y') }} The Argyle Buzz. Rooted here, growing together.</p>
    </div>
</footer>
