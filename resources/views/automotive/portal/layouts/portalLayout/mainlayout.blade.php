<!DOCTYPE html>

@php
    $isPortalGuestRoute = Route::is([
        'automotive.login',
        'automotive.password.request',
        'automotive.password.reset',
        'automotive.register',
    ]);
@endphp

@if (!Route::is(['layout-mini', 'layout-rtl', 'layout-single', 'layout-transparent', 'layout-without-header', 'layout-dark']))
    <html lang="en">
@endif

@if (Route::is(['layout-mini']))
    <html lang="en" data-layout="mini">
@endif

@if (Route::is(['layout-dark']))
    <html lang="en" data-bs-theme="dark" data-sidebar="light" data-color="primary" data-topbar="white" data-layout="default" data-size="default" data-width="fluid">
@endif

@if (Route::is(['layout-rtl']))
    <html lang="en" dir="rtl">
@endif

@if (Route::is(['layout-single']))
    <html lang="en" data-layout="single">
@endif

@if (Route::is(['layout-transparent']))
    <html lang="en" data-layout="transparent">
@endif

@if (Route::is(['layout-without-header']))
    <html lang="en" data-layout="without-header">
@endif

@component('automotive.portal.layouts.portalLayout.components.title-meta')
@endcomponent

@if (!Route::is([
    'coming-soon',
    'error-404',
    'error-500',
    'under-construction',
    'under-maintenance',
    'layout-mini',
    'layout-rtl',
    'lock-screen',
    'success',
    'two-step-verification',
    'email-verification'
]) && ! $isPortalGuestRoute)
    <body>
@endif

@if(Route::is(['error-404', 'error-500', 'under-construction', 'under-maintenance', 'coming-soon']))
    <body class="bg-white coming-soon">
@endif

@if($isPortalGuestRoute || Route::is(['lock-screen']))
    <body class="bg-white">
@endif

@if (Route::is(['layout-mini']))
    <body class="mini-sidebar">
@endif

@if (Route::is(['layout-rtl']))
    <body class="layout-mode-rtl">
@endif

@if(Route::is(['general-invoice-5']))
    <body class="bg-dark">
@endif

@if(!Route::is([
    'error-404',
    'error-500',
    'lock-screen',
    'success',
    'two-step-verification',
    'under-construction',
    'under-maintenance',
    'coming-soon',
    'email-verification'
]) && ! $isPortalGuestRoute)
    <div class="main-wrapper">
@endif

@if($isPortalGuestRoute || Route::is([
    'error-404',
    'coming-soon',
    'error-500',
    'lock-screen',
    'success',
    'two-step-verification',
    'under-construction',
    'under-maintenance',
    'email-verification'
]))
    <div class="main-wrapper auth-bg">
@endif

@if (!Route::is([
    'signin',
    'signup',
    'coming-soon',
    'error-404',
    'error-500',
    'under-construction',
    'change-password',
    'forgot-password',
    'lock-screen',
    'general-invoice-1',
    'general-invoice-1a',
    'general-invoice-2',
    'general-invoice-2a',
    'general-invoice-3',
    'general-invoice-4',
    'general-invoice-5',
    'general-invoice-6',
    'general-invoice-7',
    'general-invoice-8',
    'general-invoice-9',
    'general-invoice-10',
    'hotel-booking-invoice',
    'domain-hosting-invoice',
    'ecommerce-invoice',
    'internet-billing-invoice',
    'invoice-medical',
    'receipt-invoice-1',
    'receipt-invoice-2',
    'receipt-invoice-3',
    'receipt-invoice-4',
    'email-verification',
    'money-exchange-invoice',
    'movie-ticket-booking-invoice',
    'student-billing-invoice',
    'success',
    'train-ticket-invoice',
    'two-step-verification',
    'under-maintenance',
    'bus-booking-invoice',
    'car-booking-invoice',
    'coffee-shop-invoice',
    'fitness-center-invoice',
    'flight-booking-invoice',
    'restaurants-invoice'
]) && ! $isPortalGuestRoute)
    @include('automotive.portal.layouts.portalLayout.partials.header')
@endif

@if(!Route::is([
    'signin',
    'signup',
    'coming-soon',
    'error-404',
    'error-500',
    'under-construction',
    'change-password',
    'forgot-password',
    'lock-screen',
    'general-invoice-1',
    'general-invoice-1a',
    'general-invoice-2',
    'general-invoice-2a',
    'general-invoice-3',
    'general-invoice-4',
    'general-invoice-5',
    'general-invoice-6',
    'general-invoice-7',
    'general-invoice-8',
    'general-invoice-9',
    'general-invoice-10',
    'hotel-booking-invoice',
    'domain-hosting-invoice',
    'ecommerce-invoice',
    'internet-billing-invoice',
    'invoice-medical',
    'receipt-invoice-1',
    'receipt-invoice-2',
    'receipt-invoice-3',
    'receipt-invoice-4',
    'email-verification',
    'money-exchange-invoice',
    'movie-ticket-booking-invoice',
    'student-billing-invoice',
    'success',
    'train-ticket-invoice',
    'two-step-verification',
    'under-maintenance',
    'bus-booking-invoice',
    'car-booking-invoice',
    'coffee-shop-invoice',
    'fitness-center-invoice',
    'flight-booking-invoice',
    'restaurants-invoice'
]) && ! $isPortalGuestRoute)
    @include('automotive.portal.layouts.portalLayout.partials.sidebar')
@endif

@php($page = $page ?? '')
@yield('content')

@component('automotive.portal.layouts.portalLayout.components.modal-popup')
@endcomponent
</div>

@include('automotive.portal.layouts.portalLayout.partials.footer-scripts')
</body>
</html>
