@extends('layouts.app')

@section('title', 'Log in')
@section('description', 'Log in to your Rediscover Roanoke account.')

@section('content')
    <section class="mx-auto max-w-xl px-5 py-14 sm:px-8 sm:py-20">
        <div class="rounded-xl border border-[var(--rr-border)] bg-white p-7 shadow-sm sm:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Rediscover Roanoke</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-[var(--rr-neutral-dark)]">Welcome back</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">Log in to continue to your account.</p>

            @if ($errors->any())
                <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">We couldn't log you in.</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" required autofocus
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] placeholder:text-[var(--rr-neutral-mid)] focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20"
                        placeholder="you@example.com">
                    @error('email')
                        <p id="email-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <label for="password" class="block text-sm font-semibold text-[var(--rr-neutral-dark)]">Password</label>
                        <a href="{{ route('password.request') }}" class="min-h-11 rounded-sm py-2 text-sm font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4 hover:text-[var(--rr-primary-light)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-primary)]">Forgot password?</a>
                    </div>
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                        class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                    @error('password')
                        <p id="password-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <label for="remember" class="flex min-h-11 items-center gap-3 text-sm text-[var(--rr-neutral-dark)]">
                    <input id="remember" name="remember" type="checkbox" value="1" class="size-4 rounded border-[var(--rr-border)] text-[var(--rr-primary)] focus:ring-[var(--rr-primary)]">
                    Remember me
                </label>

                <button type="submit" class="min-h-11 w-full rounded-lg bg-[var(--rr-highlight)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">
                    Log in
                </button>
            </form>

            <p class="mt-7 text-center text-sm text-[var(--rr-neutral-mid)]">
                Not a member yet?
                <a href="{{ route('register') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4 hover:text-[var(--rr-primary-light)]">Join now</a>
            </p>
        </div>
    </section>
@endsection
