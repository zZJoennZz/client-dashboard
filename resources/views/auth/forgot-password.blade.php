@extends('layouts.app')

@section('title', 'Forgot password')
@section('description', 'Request a secure link to reset your Argyle Buzz account password.')

@section('content')
    <section class="mx-auto max-w-xl px-5 py-14 sm:px-8 sm:py-20">
        <div class="rounded-xl border border-[var(--rr-border)] bg-white p-7 shadow-sm sm:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Account recovery</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-[var(--rr-neutral-dark)]">Reset your password</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">Enter the email address associated with your account and we’ll send you a password reset link.</p>

            @if (session('status'))
                <p role="status" class="mt-6 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-900">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">We couldn’t send the reset link.</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-5">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" required autofocus
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] placeholder:text-[var(--rr-neutral-mid)] focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20"
                        placeholder="you@example.com">
                    @error('email')
                        <p id="email-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="min-h-11 w-full rounded-lg bg-[var(--rr-highlight)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">
                    Email password reset link
                </button>
            </form>

            <p class="mt-7 text-center text-sm text-[var(--rr-neutral-mid)]">
                Remembered your password?
                <a href="{{ route('login') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4 hover:text-[var(--rr-primary-light)]">Return to login</a>
            </p>
        </div>
    </section>
@endsection
