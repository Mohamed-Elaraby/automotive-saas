<?php $page = 'dashboard'; ?>
@extends('automotive.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">

            Welcome {{ auth('web')->user()->name }}

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
