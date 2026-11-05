<a href="#main-content" class="sr-only z-50 rounded-full bg-[var(--rr-primary)] px-5 py-3 text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
    Skip to content
</a>
<header class="sticky top-0 z-40 border-b border-[var(--rr-border)] bg-[var(--rr-background)]/95 shadow-sm backdrop-blur">
    <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8" aria-label="Main navigation">
        <a href="{{ route('home') }}" class="flex w-fit items-center gap-3 rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]">
            <img src="{{ asset('the-argyle-buzz-logo.png') }}" alt="The Argyle Buzz" width="475" height="88" class="h-11 w-auto max-w-[42vw] object-contain">
        </a>
        <div class="hidden items-center gap-6 text-sm font-medium text-[var(--rr-neutral-mid)] lg:flex">
            <a href="{{ route('benefits') }}#how-it-works" @class(['rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]', 'text-[var(--rr-highlight)]' => request()->routeIs('benefits')])>How it works</a>
            <a href="{{ route('benefits') }}" @class(['rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]', 'text-[var(--rr-highlight)]' => request()->routeIs('benefits')])>Benefits</a>
            <a href="{{ route('pricing') }}" @class(['rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]', 'text-[var(--rr-highlight)]' => request()->routeIs('pricing')])>Pricing</a>
            <a href="{{ route('home') }}#faq" class="rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]">FAQ</a>
            <a href="{{ route('businesses.index') }}" @class(['rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]', 'text-[var(--rr-highlight)]' => request()->routeIs('businesses.*')])>Directory</a>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="min-h-11 rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-sm hover:text-[var(--rr-highlight)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--rr-highlight)]">Login</a>
                <a href="{{ route('register') }}" class="min-h-11 rounded-lg bg-[var(--rr-highlight)] px-5 py-3 font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">Join now</a>
            @endauth
        </div>
        <details class="relative lg:hidden">
            <summary class="flex min-h-11 min-w-11 cursor-pointer list-none items-center justify-center rounded-lg border border-[var(--rr-border)] bg-white px-3 text-sm font-semibold text-[var(--rr-primary)] hover:bg-[var(--rr-neutral-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]" aria-label="Navigation menu">
                <span class="text-lg leading-none" aria-hidden="true">☰</span>
                <span class="sr-only">Menu</span>
            </summary>
            <div class="absolute right-0 top-14 z-50 flex w-[min(18rem,calc(100vw-2.5rem))] flex-col gap-1 rounded-xl border border-[var(--rr-border)] bg-white p-3 text-sm font-medium text-[var(--rr-neutral-dark)] shadow-lg">
                <a href="{{ route('benefits') }}#how-it-works" class="min-h-11 rounded-lg px-3 py-3 hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">How it works</a>
                <a href="{{ route('benefits') }}" class="min-h-11 rounded-lg px-3 py-3 hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">Benefits</a>
                <a href="{{ route('pricing') }}" class="min-h-11 rounded-lg px-3 py-3 hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">Pricing</a>
                <a href="{{ route('home') }}#faq" class="min-h-11 rounded-lg px-3 py-3 hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">FAQ</a>
                <a href="{{ route('businesses.index') }}" class="min-h-11 rounded-lg px-3 py-3 hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">Directory</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="min-h-11 rounded-lg px-3 py-3 hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-11 w-full rounded-lg px-3 py-3 text-left hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="min-h-11 rounded-lg px-3 py-3 hover:bg-[var(--rr-accent-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--rr-primary)]">Login</a>
                    <a href="{{ route('register') }}" class="mt-1 min-h-11 rounded-lg bg-[var(--rr-highlight)] px-3 py-3 text-center font-semibold text-white hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">Join now</a>
                @endauth
            </div>
        </details>
    </nav>
</header>
