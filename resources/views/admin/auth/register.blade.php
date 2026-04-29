<?php $page = 'register'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap ">
                <div class="col-lg-4 mx-auto">
                    <form method="POST" action="{{ route('admin.register.submit') }}" class="d-flex justify-content-center align-items-center">
                        @csrf

                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pt-lg-4 pb-0 flex-fill">
                            <div class="mx-auto mb-5 text-center">
                                <img src="{{ asset('theme/img/logo.svg') }}" class="img-fluid" alt="Logo">
                            </div>

                            <div class="card border-0 p-lg-3 shadow-lg rounded-2">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <h5 class="mb-2">{{ __('admin.sign_up') }}</h5>
                                        <p class="mb-0">{{ __('admin.sign_up_intro') }}</p>
                                    </div>

                                    @if($errors->any())
                                        <div class="alert alert-danger">
                                            <ul class="mb-0">
                                                @foreach($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('admin.full_name') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-profile"></i>
                                            </span>
                                            <input
                                                type="text"
                                                name="name"
                                                value="{{ old('name') }}"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="{{ __('admin.name_placeholder') }}"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('admin.email_address') }}</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-sms-notification"></i>
                                            </span>
                                            <input
                                                type="email"
                                                name="email"
                                                value="{{ old('email') }}"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="{{ __('admin.enter_email_address') }}"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('admin.password') }}</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-password isax-eye-slash"></span>
                                            <input
                                                type="password"
                                                name="password"
                                                class="pass-input form-control border-start-0 ps-0"
                                                placeholder="****************"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">{{ __('admin.confirm_password') }}</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-passwords isax-eye-slash"></span>
                                            <input
                                                type="password"
                                                name="password_confirmation"
                                                class="pass-input form-control border-start-0 ps-0"
                                                placeholder="****************"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="form-check form-check-md mb-0">
                                                <input class="form-check-input" id="remember_me" type="checkbox" checked disabled>
                                                <label for="remember_me" class="form-check-label mt-0">{{ __('admin.agree_to') }}</label>
                                                <div class="d-inline-flex">
                                                    <a href="#" class="text-decoration-underline me-1">{{ __('admin.terms_of_service') }}</a>
                                                    {{ __('admin.and') }}
                                                    <a href="#" class="text-decoration-underline ms-1">{{ __('admin.privacy_policy') }}</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">{{ __('admin.sign_up') }}</button>
                                    </div>

                                    <div class="login-or">
                                        <span class="span-or">{{ __('admin.or') }}</span>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex align-items-center justify-content-center flex-wrap">
                                            <div class="text-center me-2 flex-fill">
                                                <a href="javascript:void(0);" class="br-10 p-1 btn btn-light d-flex align-items-center justify-content-center">
                                                    <img class="img-fluid m-1" src="{{ asset('theme/img/icons/facebook-logo.svg') }}" alt="Facebook">
                                                </a>
                                            </div>
                                            <div class="text-center me-2 flex-fill">
                                                <a href="javascript:void(0);" class="br-10 p-1 btn btn-light d-flex align-items-center justify-content-center">
                                                    <img class="img-fluid m-1" src="{{ asset('theme/img/icons/google-logo.svg') }}" alt="Google">
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-center">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">
                                            {{ __('admin.already_have_account') }}
                                            <a href="{{ route('admin.login') }}" class="hover-a"> {{ __('admin.sign_in') }}</a>
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
