<?php $page = 'automotive/admin/login'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')
@section('content')

    <!-- ========================
        Start Page Content
    ========================= -->

    <!-- Start container -->
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap ">
                <div class="col-lg-4 mx-auto">
{{--                    <form method="POST" action="{{ route('automotive.admin.login.submit') }}" class="d-flex justify-content-center align-items-center">--}}
                    <form method="POST" action="{{ url('/automotive/admin/login') }}" class="d-flex justify-content-center align-items-center">                        @csrf

                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
                            <div class=" mx-auto mb-5 text-center">
                                Automotive Saas Login
                            </div>

                            <div class="card border-0 p-lg-3 shadow-lg">
                                <div class="card-body">

                                    @if(session('status'))
                                        <div class="alert alert-success mb-3">{{ session('status') }}</div>
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

                                    <div class="text-center mb-3">
                                        <h5 class="mb-2">Sign In</h5>
                                        <p class="mb-0">Please enter below details to access the dashboard</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-sms-notification"></i>
                                            </span>
                                            <input
                                                type="email"
                                                value="{{ old('email') }}"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="Enter Email Address"
                                                name="email"
                                                required
                                                autofocus
                                            >
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-password isax-eye-slash"></span>
                                            <input
                                                type="password"
                                                class="pass-inputs form-control border-start-0 ps-0"
                                                placeholder="****************"
                                                name="password"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="form-check form-check-md mb-0">
                                            <input class="form-check-input" id="remember_me" name="remember" type="checkbox" {{ old('remember') ? 'checked' : '' }}>
                                            <label for="remember_me" class="form-check-label mt-0">Remember Me</label>
                                        </div>

                                        <div class="text-end">
                                            @if (\Illuminate\Support\Facades\Route::has('automotive.admin.forgot'))
                                                <a href="{{ route('automotive.admin.forgot') }}">Forgot Password</a>
                                            @endif                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">Sign In</button>
                                    </div>

                                    <div class="text-center mt-3">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">Don’t have an account yet?
                                            @if (\Illuminate\Support\Facades\Route::has('automotive.admin.register'))
                                                <a href="{{ route('automotive.admin.register') }}" class="hover-a"> Register</a>
                                            @endif
                                        </h6>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </form>
                    {{--<form action="{{url('index')}}" class="d-flex justify-content-center align-items-center">
                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
                            <div class=" mx-auto mb-5 text-center">
--}}{{--                                <img src="{{ asset('theme/img/logo.svg')}}" class="img-fluid" alt="Logo">--}}{{--
                                Automotive Saas Login
                            </div>
                            <div class="card border-0 p-lg-3 shadow-lg">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <h5 class="mb-2">Sign In</h5>
                                        <p class="mb-0">Please enter below details to access the dashboard</p>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-sms-notification"></i>
                                            </span>
                                            <input type="text" value="" class="form-control border-start-0 ps-0" placeholder="Enter Email Address" name="email">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <div class="pass-group input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-lock"></i>
                                            </span>
                                            <span class="isax toggle-password isax-eye-slash"></span>
                                            <input type="password" class="pass-inputs form-control border-start-0 ps-0" placeholder="****************" name="password">
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="form-check form-check-md mb-0">
                                                <input class="form-check-input" id="remember_me" type="checkbox">
                                                <label for="remember_me" class="form-check-label mt-0">Remember Me</label>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <a href="{{url('forgot-password')}}">Forgot Password</a>
                                        </div>
                                    </div>
                                    <div class="mb-1">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">Sign In</button>
                                    </div>
                                    <div class="login-or">
                                        <span class="span-or">Or</span>
                                    </div>
                                    --}}{{--<div class="mb-3">
                                        <div class="d-flex align-items-center justify-content-center flex-wrap">
                                            <div class="text-center me-2 flex-fill">
                                                <a href="javascript:void(0);"
                                                   class="br-10 p-1 btn btn-light d-flex align-items-center justify-content-center">
                                                    <img class="img-fluid m-1" src="{{ asset('theme/img/icons/facebook-logo.svg')}}" alt="Facebook">
                                                </a>
                                            </div>
                                            <div class="text-center me-2 flex-fill">
                                                <a href="javascript:void(0);"
                                                   class="br-10 p-1 btn btn-light d-flex align-items-center justify-content-center">
                                                    <img class="img-fluid m-1" src="{{ asset('theme/img/icons/google-logo.svg')}}" alt="Google">
                                                </a>
                                            </div>
                                        </div>
                                    </div>--}}{{--
                                    <div class="text-center">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">Don’t have an account yet?
                                            <a href="{{url('automotive.admin.register')}}" class="hover-a"> Register</a>
                                        </h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>--}}
                </div>
            </div>
        </div>
    </div>

    <!-- End container -->

    <!-- ========================
        End Page Content
    ========================= -->
@endsection

