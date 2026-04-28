<?php $page = 'automotive/portal/login'; ?>
@extends('automotive.portal.layouts.portalLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap ">
                <div class="col-lg-4 mx-auto">
                    <form method="POST" action="{{ route('automotive.login.submit') }}" class="d-flex justify-content-center align-items-center">
                        @csrf

                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
                            <div class="mx-auto mb-5 text-center">
                                {{ __('portal.brand') }}
                                <div class="mt-3 d-inline-flex">
                                    @include('shared.partials.language-switcher')
                                </div>
                            </div>

                            <div class="card border-0 p-lg-3 shadow-lg">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <h5 class="mb-2">{{ __('shared.sign_in') }}</h5>
                                        <p class="mb-0">{{ __('portal.sign_in_intro') }}</p>
                                    </div>

                                    @if(session('success'))
                                        <div class="alert alert-success mb-3">{{ session('success') }}</div>
                                    @endif

                                    @if($errors->any())
                                        <div class="alert alert-danger mb-3">
                                            <ul class="mb-0 ps-3">
                                                @foreach($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.business_email') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-sms-notification"></i>
                                            </span>
                                            <input
                                                id="email"
                                                type="email"
                                                name="email"
                                                value="{{ old('email') }}"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="{{ __('portal.enter_email_address') }}"
                                                required
                                                autofocus
                                            >
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('portal.password') }}</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-password isax-eye-slash"></span>
                                            <input
                                                id="password"
                                                type="password"
                                                name="password"
                                                class="pass-inputs form-control border-start-0 ps-0"
                                                placeholder="****************"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="form-check form-check-md mb-0">
                                            <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }}>
                                            <label for="remember" class="form-check-label mt-0">{{ __('portal.remember_me') }}</label>
                                        </div>

                                        <div class="text-end">
                                            <a href="{{ route('automotive.password.request') }}">{{ __('portal.forgot_password') }}</a>
                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">{{ __('portal.sign_in_to_portal') }}</button>
                                    </div>

                                    <div class="text-center mt-3">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">
                                            {{ __('portal.need_account') }}
                                            <a href="{{ route('automotive.register') }}" class="hover-a"> {{ __('portal.create_one_here') }}</a>
                                        </h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
