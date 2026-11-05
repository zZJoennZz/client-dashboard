<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F9FAFB">
    <title>@yield('title', 'The Argyle Buzz') · The Argyle Buzz</title>
    <meta name="description" content="@yield('description', 'Discover and support independent businesses in Argyle.')">
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    @hasSection('social_image')
        <meta property="og:image" content="@yield('social_image')">
    @endif
    <meta property="og:title" content="@yield('title', 'The Argyle Buzz')">
    <meta property="og:description" content="@yield('description', 'Discover and support independent businesses in Argyle.')">
    <meta property="og:type" content="@yield('og_type', 'website')">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[var(--rr-background)] font-sans text-[var(--rr-neutral-dark)] antialiased">
    @include('partials.navbar')

    <main id="main-content">
        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>
