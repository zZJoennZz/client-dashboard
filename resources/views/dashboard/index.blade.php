<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F9FAFB">
    <title>Member dashboard · Rediscover Roanoke</title>
    <meta name="description" content="Your Rediscover Roanoke member workspace.">
    @vite(['resources/css/app.css', 'resources/js/dashboard/main.jsx'])
</head>
<body class="min-h-screen bg-[var(--rr-background)] font-sans text-[var(--rr-neutral-dark)] antialiased">
    <div id="member-dashboard" data-dashboard-data-url="{{ route('dashboard.data') }}" data-login-url="{{ route('login') }}"></div>
</body>
</html>
