<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F9FAFB">
    <title>Service Provider · Rediscover Roanoke</title>
    <meta name="description" content="Platform-wide service provider administration for Rediscover Roanoke.">
    @vite(['resources/css/app.css', 'resources/js/super-admin/main.jsx'])
</head>
<body class="min-h-screen bg-[var(--rr-background)] font-sans text-[var(--rr-neutral-dark)] antialiased">
    <div
        id="super-admin"
        data-overview-url="{{ route('super-admin.overview') }}"
        data-settings-url="{{ route('super-admin.settings.show') }}"
        data-settings-update-url="{{ route('super-admin.settings.membership-pricing.update') }}"
        data-engagement-url="{{ route('super-admin.engagement') }}"
        data-businesses-url="{{ route('super-admin.businesses.index') }}"
        data-accounts-url="{{ route('super-admin.accounts.index') }}"
        data-workspace-url="{{ url('/super-admin/api/workspace') }}"
        data-memberships-url="{{ url('/super-admin/api/memberships') }}"
        data-resources-url="{{ url('/super-admin/api/resources') }}"
        data-reports-url="{{ url('/super-admin/api/reports') }}"
        data-events-url="{{ url('/super-admin/api/events') }}"
        data-marketing-url="{{ url('/super-admin/api/marketing') }}"
        data-services-url="{{ url('/super-admin/api/services') }}"
        data-orders-url="{{ url('/super-admin/api/orders') }}"
        data-announcements-url="{{ url('/super-admin/api/announcements') }}"
        data-export-url="{{ url('/super-admin/api/exports') }}"
        data-login-url="{{ route('login') }}"
    ></div>
</body>
</html>
