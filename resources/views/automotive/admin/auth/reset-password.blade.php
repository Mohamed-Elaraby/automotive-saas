<?php $page = 'automotive/admin/reset'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap">
                <div class="col-lg-4 mx-auto">
                    <form method="POST" action="{{ route('automotive.admin.password.update') }}" class="d-flex justify-content-center align-items-center">
                        @csrf

                        <input type="hidden" name="token" value="{{ $token ?? request()->route('token') }}">

                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pb-0 flex-fill">
                            <div class="mx-auto mb-5 text-center">
                                Automotive SaaS
                            </div>

                            <div class="card border-0 p-lg-3 shadow-lg rounded-2">
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
                                        <h5 class="mb-2">Reset Password</h5>
                                        <p class="mb-0">Enter your email and new password</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-sms-notification"></i>
                                            </span>
                                            <input
                                                type="email"
                                                name="email"
                                                value="{{ old('email', $email ?? request('email')) }}"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="Enter Email Address"
                                                required
                                            >
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
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
                                        <label class="form-label">Confirm Password</label>
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

                                    <div class="mb-3">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">Reset Password</button>
                                    </div>

                                    <div class="text-center">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">
                                            Return to
                                            <a href="{{ route('automotive.admin.login') }}" class="hover-a"> Sign In</a>
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
