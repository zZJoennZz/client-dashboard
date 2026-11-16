<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#F9FAFB">
    <title>{{ $roleLabel }} · The Argyle Buzz</title>
    <meta name="description" content="Platform-wide system administration for The Argyle Buzz.">
    @vite(['resources/css/app.css', 'resources/js/super-admin/main.jsx'])
</head>
<body class="min-h-screen bg-[var(--rr-background)] font-sans text-[var(--rr-neutral-dark)] antialiased">
    <div
        id="super-admin"
        data-module-access='@json($moduleAccess)'
        data-role-label="{{ $roleLabel }}"
        data-can-manage-system-roles="{{ $canManageSystemRoles ? 'true' : 'false' }}"
        data-overview-url="{{ route('super-admin.overview') }}"
        data-settings-url="{{ route('super-admin.settings.show') }}"
        data-settings-update-url="{{ route('super-admin.settings.membership-pricing.update') }}"
        data-engagement-url="{{ route('super-admin.engagement') }}"
        data-businesses-url="{{ route('super-admin.businesses.index') }}"
        data-accounts-url="{{ route('super-admin.accounts.index') }}"
        data-workspace-url="{{ url('/system/api/workspace') }}"
        data-memberships-url="{{ url('/system/api/memberships') }}"
        data-resources-url="{{ url('/system/api/resources') }}"
        data-reports-url="{{ url('/system/api/reports') }}"
        data-events-url="{{ url('/system/api/events') }}"
        data-marketing-url="{{ url('/system/api/marketing') }}"
        data-services-url="{{ url('/system/api/services') }}"
        data-orders-url="{{ url('/system/api/orders') }}"
        data-announcements-url="{{ url('/system/api/announcements') }}"
        data-export-url="{{ url('/system/api/exports') }}"
        data-access-roles-url="{{ url('/system/api/access-roles') }}"
        data-login-url="{{ route('login') }}"
    ></div>
</body>
</html>
