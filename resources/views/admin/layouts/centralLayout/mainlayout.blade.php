<!DOCTYPE html>

@php
    $isAdminAuthRoute = Route::is([
        'admin.login',
        'admin.register',
        'admin.password.request',
        'admin.password.reset',
    ]);
@endphp

@if (!Route::is(['layout-mini', 'layout-rtl', 'layout-single', 'layout-transparent', 'layout-without-header', 'layout-dark']))
    <html lang="{{ app()->getLocale() }}" dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}">
@endif

@if (Route::is(['layout-mini']))
    <html lang="{{ app()->getLocale() }}" dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}" data-layout="mini">
@endif

@if (Route::is(['layout-dark']))
    <html lang="{{ app()->getLocale() }}" dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}" data-bs-theme="dark" data-sidebar="light" data-color="primary" data-topbar="white" data-layout="default" data-size="default" data-width="fluid">
@endif

@if (Route::is(['layout-rtl']))
    <html lang="{{ app()->getLocale() }}" dir="rtl">
@endif

@if (Route::is(['layout-single']))
    <html lang="{{ app()->getLocale() }}" dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}" data-layout="single">
@endif

@if (Route::is(['layout-transparent']))
    <html lang="{{ app()->getLocale() }}" dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}" data-layout="transparent">
@endif

@if (Route::is(['layout-without-header']))
    <html lang="{{ app()->getLocale() }}" dir="{{ LaravelLocalization::getCurrentLocaleDirection() }}" data-layout="without-header">
@endif

@component('admin.layouts.components.title-meta')
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
]) && ! $isAdminAuthRoute)
    <body>
@endif

@if(Route::is(['error-404', 'error-500', 'under-construction', 'under-maintenance', 'coming-soon']))
    <body class="bg-white coming-soon">
@endif

@if($isAdminAuthRoute || Route::is(['lock-screen']))
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
]) && ! $isAdminAuthRoute)
    <div class="main-wrapper">
@endif

@if($isAdminAuthRoute || Route::is([
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
]) && ! $isAdminAuthRoute)
    @include('admin.layouts.centralLayout.partials.header')
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
]) && ! $isAdminAuthRoute)
    @include('admin.layouts.centralLayout.partials.sidebar')
@endif

@php($page = $page ?? '')
@yield('content')

</div>

@include('admin.layouts.centralLayout.partials.footer-scripts')
</body>
</html>
