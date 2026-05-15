<?php $page = 'access-control'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="mb-1">Access Diagnostic Result</h4>
                    <p class="mb-0 text-muted">{{ $user->name }} - {{ $user->email }}</p>
                </div>
                <a href="{{ route('automotive.admin.access.diagnostics.index') }}" class="btn btn-outline-white">
                    <i class="isax isax-arrow-left me-1"></i>Back
                </a>
            </div>

            @include('automotive.admin.access.diagnostics.partials._diagnostic-result', ['result' => $result])
        </div>
    </div>
@endsection
