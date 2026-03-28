<?php $page = 'automotive/admin/register'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="container-fuild">
        <div class="w-100 overflow-hidden position-relative flex-wrap d-block vh-100">
            <div class="row justify-content-center align-items-center vh-100 overflow-auto flex-wrap">
                <div class="col-lg-4 mx-auto">
                    <form method="POST" action="{{ route('automotive.admin.register.submit') }}" class="d-flex justify-content-center align-items-center">
                        @csrf

                        <div class="d-flex flex-column justify-content-lg-center p-4 p-lg-0 pt-lg-4 pb-0 flex-fill">
                            <div class="mx-auto mb-5 text-center">
                                Automotive SaaS Register
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
                                        <h5 class="mb-2">Sign Up</h5>
                                        <p class="mb-0">Please enter your details to create your tenant admin account</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text border-end-0">
                                                <i class="isax isax-profile"></i>
                                            </span>
                                            <input
                                                type="text"
                                                name="name"
                                                value="{{ old('name') }}"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="Enter Full Name"
                                                required
                                                autofocus
                                            >
                                        </div>
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
                                                value="{{ old('email') }}"
                                                class="form-control border-start-0 ps-0"
                                                placeholder="Enter Email Address"
                                                required
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

                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="form-check form-check-md mb-0">
                                            <input class="form-check-input" id="agree_terms" type="checkbox" required>
                                            <label for="agree_terms" class="form-check-label mt-0">
                                                I agree to the Terms of Service and Privacy Policy
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mb-1">
                                        <button type="submit" class="btn bg-primary-gradient text-white w-100">Sign Up</button>
                                    </div>

                                    <div class="text-center mt-3">
                                        <h6 class="fw-normal fs-14 text-dark mb-0">
                                            Already have an account?
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
