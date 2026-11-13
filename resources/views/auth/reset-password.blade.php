@extends('layouts.app')

@section('title', 'Choose a new password')
@section('description', 'Choose a new password for your Argyle Buzz account.')

@section('content')
    <section class="mx-auto max-w-xl px-5 py-14 sm:px-8 sm:py-20">
        <div class="rounded-xl border border-[var(--rr-border)] bg-white p-7 shadow-sm sm:p-10">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">Account recovery</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight text-[var(--rr-neutral-dark)]">Choose a new password</h1>
            <p class="mt-2 text-sm leading-6 text-[var(--rr-neutral-mid)]">Use a new password to secure your account.</p>

            @if ($errors->any())
                <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">We couldn’t reset your password.</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" class="mt-7 space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <div>
                    <label for="email" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $request->query('email')) }}" autocomplete="email" required
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm text-[var(--rr-neutral-dark)] focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                    @error('email')
                        <p id="email-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                        class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                    @error('password')
                        <p id="password-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-[var(--rr-neutral-dark)]">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                        class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                </div>
                <button type="submit" class="min-h-11 w-full rounded-lg bg-[var(--rr-highlight)] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">
                    Reset password
                </button>
            </form>
        </div>
    </section>
@endsection
