<!DOCTYPE html>

@php
    $tenantAdminAuthRoutes = [
        'automotive.admin.login',
        'automotive.admin.subscription.expired',
    ];

    $isTenantAdminAuthRoute = Route::is($tenantAdminAuthRoutes);
@endphp

<html lang="en">

@component('automotive.admin.layouts.components.title-meta')
@endcomponent

<body class="{{ $isTenantAdminAuthRoute ? 'bg-white' : '' }}">
<div class="main-wrapper{{ $isTenantAdminAuthRoute ? ' auth-bg' : '' }}">
    @if (! $isTenantAdminAuthRoute)
        @include('automotive.admin.layouts.adminLayout.partials.header')
        @include('automotive.admin.layouts.adminLayout.partials.sidebar')
    @endif

    @php($page = $page ?? '')
    @yield('content')
</div>

@include('automotive.admin.layouts.adminLayout.partials.footer-scripts')
</body>
</html>
