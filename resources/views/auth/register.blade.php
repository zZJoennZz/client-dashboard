@extends('layouts.app')

@section('title', 'Join The Argyle Buzz')
@section('description', 'Register your local business with The Argyle Buzz Local Business Growth Initiative.')

@section('content')
    <section class="mx-auto max-w-4xl px-5 py-12 sm:px-8 sm:py-16">
        <div class="mx-auto max-w-2xl text-center">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-[var(--rr-accent)]">A stronger local economy starts here</p>
            <h1 class="mt-3 text-4xl font-semibold tracking-tight text-[var(--rr-neutral-dark)] sm:text-5xl">Bring your business into the community.</h1>
            <p class="mt-4 text-base leading-7 text-[var(--rr-neutral-mid)]">Create an account to join local owners building a more connected Argyle.</p>
        </div>

        <ol class="mx-auto mt-8 grid max-w-2xl grid-cols-3 gap-2 text-center text-xs font-semibold text-[var(--rr-neutral-mid)] sm:gap-4 sm:text-sm" aria-label="Registration steps">
            <li class="rounded-lg bg-[var(--rr-accent-light)] px-2 py-3 text-[var(--rr-accent)]"><span class="block text-base">1</span>Business details</li>
            <li class="rounded-lg bg-[var(--rr-neutral-light)] px-2 py-3"><span class="block text-base">2</span>Account</li>
            <li class="rounded-lg bg-[var(--rr-neutral-light)] px-2 py-3"><span class="block text-base">3</span>Membership</li>
        </ol>

        <div class="mt-8 rounded-xl border border-[var(--rr-border)] bg-white p-6 shadow-sm sm:p-9">
            @if ($errors->any())
                <div role="alert" class="mb-7 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <p class="font-semibold">Please check the information below.</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="space-y-8">
                @csrf

                <fieldset>
                    <legend class="text-lg font-semibold text-[var(--rr-neutral-dark)]">Business information</legend>
                    <p class="mt-1 text-sm text-[var(--rr-neutral-mid)]">You can update these details later.</p>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="company_name" class="mb-2 block text-sm font-semibold">Business name <span aria-hidden="true">*</span></label>
                            <input id="company_name" name="company_name" type="text" value="{{ old('company_name') }}" maxlength="255" autocomplete="organization" required autofocus
                                @error('company_name') aria-invalid="true" aria-describedby="company-name-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('company_name')<p id="company-name-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="category" class="mb-2 block text-sm font-semibold">Business category <span aria-hidden="true">*</span></label>
                            <select id="category" name="category" required
                                @error('category') aria-invalid="true" aria-describedby="category-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                                <option value="" disabled @selected(old('category') === null)>Choose a category</option>
                                @foreach (config('business_categories.standard') as $categoryOption)
                                    <option value="{{ $categoryOption }}" @selected(old('category') === $categoryOption)>{{ $categoryOption }}</option>
                                @endforeach
                                <option value="Other" @selected(old('category') === 'Other')>Other</option>
                            </select>
                            @error('category')<p id="category-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div id="category-other-field" @if (old('category') !== 'Other') hidden @endif>
                            <label for="category_other" class="mb-2 block text-sm font-semibold">Your business category <span aria-hidden="true">*</span></label>
                            <input id="category_other" name="category_other" type="text" value="{{ old('category_other') }}" maxlength="100"
                                @if (old('category') === 'Other') required @endif
                                @error('category_other') aria-invalid="true" aria-describedby="category-other-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('category_other')<p id="category-other-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="website" class="mb-2 block text-sm font-semibold">Website</label>
                            <input id="website" name="website" type="url" value="{{ old('website') }}" maxlength="255" autocomplete="url" placeholder="https://example.com"
                                @error('website') aria-invalid="true" aria-describedby="website-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm placeholder:text-[var(--rr-neutral-mid)] focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('website')<p id="website-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="description" class="mb-2 block text-sm font-semibold">About your business</label>
                            <textarea id="description" name="description" rows="3" maxlength="2000" class="w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">{{ old('description') }}</textarea>
                            @error('description')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <h3 class="text-sm font-semibold">Business address</h3>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address_line_1" class="mb-2 block text-sm font-semibold">Street address</label>
                            <input id="address_line_1" name="address_line_1" type="text" value="{{ old('address_line_1') }}" maxlength="255" autocomplete="street-address" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('address_line_1')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="city" class="mb-2 block text-sm font-semibold">City</label>
                            <input id="city" name="city" type="text" value="{{ old('city') }}" maxlength="120" autocomplete="address-level2" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('city')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="state" class="mb-2 block text-sm font-semibold">State</label>
                                <input id="state" name="state" type="text" value="{{ old('state') }}" maxlength="120" autocomplete="address-level1" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                                @error('state')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="postal_code" class="mb-2 block text-sm font-semibold">ZIP code</label>
                                <input id="postal_code" name="postal_code" type="text" value="{{ old('postal_code') }}" maxlength="30" autocomplete="postal-code" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                                @error('postal_code')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="border-t border-[var(--rr-border)] pt-7">
                    <legend class="text-lg font-semibold text-[var(--rr-neutral-dark)]">Account details</legend>
                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-2 block text-sm font-semibold">First name <span aria-hidden="true">*</span></label>
                            <input id="first_name" name="first_name" type="text" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required
                                @error('first_name') aria-invalid="true" aria-describedby="first-name-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('first_name')<p id="first-name-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="last_name" class="mb-2 block text-sm font-semibold">Last name <span aria-hidden="true">*</span></label>
                            <input id="last_name" name="last_name" type="text" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" required
                                @error('last_name') aria-invalid="true" aria-describedby="last-name-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('last_name')<p id="last-name-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="phone" class="mb-2 block text-sm font-semibold">Phone</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="40" autocomplete="tel" class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('phone')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="email" class="mb-2 block text-sm font-semibold">Email address <span aria-hidden="true">*</span></label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" inputmode="email" required
                                @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('email')<p id="email-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password" class="mb-2 block text-sm font-semibold">Password <span aria-hidden="true">*</span></label>
                            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required
                                @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                                class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                            @error('password')<p id="password-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-semibold">Confirm password <span aria-hidden="true">*</span></label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required class="min-h-11 w-full rounded-lg border border-[var(--rr-border)] bg-[var(--rr-background)] px-4 py-3 text-sm focus:border-[var(--rr-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--rr-primary)]/20">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="border-t border-[var(--rr-border)] pt-7">
                    <legend class="text-lg font-semibold text-[var(--rr-neutral-dark)]">Membership & payment</legend>
                    <div class="mt-4 rounded-lg border border-[var(--rr-border)] bg-[var(--rr-neutral-light)] p-5">
                        <p class="font-semibold text-[var(--rr-neutral-dark)]">The Argyle Buzz business membership</p>
                        <p class="mt-1 text-sm leading-6 text-[var(--rr-neutral-mid)]">Membership pricing is being finalized. Stripe checkout will be added here later. For this test registration, membership is marked active with simulated payment status; no payment is collected.</p>
                    </div>
                    <label for="terms" class="mt-5 flex items-start gap-3 text-sm leading-6 text-[var(--rr-neutral-dark)]">
                        <input id="terms" name="terms" type="checkbox" value="1" required @checked(old('terms')) class="mt-1 size-4 rounded border-[var(--rr-border)] text-[var(--rr-primary)] focus:ring-[var(--rr-primary)]">
                        <span>I confirm that I am authorized to register this business and agree to be contacted about membership. <span aria-hidden="true">*</span></span>
                    </label>
                    @error('terms')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                </fieldset>

                <button type="submit" class="min-h-12 w-full rounded-lg bg-[var(--rr-highlight)] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[var(--rr-highlight-hover)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--rr-highlight)]">
                    Create account & join
                </button>
            </form>
            <p class="mt-6 text-center text-sm text-[var(--rr-neutral-mid)]">Already registered? <a href="{{ route('login') }}" class="font-semibold text-[var(--rr-primary)] underline decoration-[var(--rr-highlight)] underline-offset-4">Log in</a></p>
        </div>
    </section>
    <script>
        const categorySelect = document.getElementById('category');
        const otherCategoryField = document.getElementById('category-other-field');
        const otherCategoryInput = document.getElementById('category_other');

        const toggleOtherCategory = () => {
            const isOther = categorySelect.value === 'Other';
            otherCategoryField.hidden = !isOther;
            otherCategoryInput.required = isOther;
            if (!isOther) otherCategoryInput.value = '';
        };

        categorySelect.addEventListener('change', toggleOtherCategory);
        toggleOtherCategory();
    </script>
@endsection
