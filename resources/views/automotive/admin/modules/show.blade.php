<?php $page = $page ?? 'module'; ?>
@extends('automotive.admin.layouts.adminLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content content-two">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h4 class="mb-1">{{ $title }}</h4>
                    <p class="mb-0 text-muted">{{ $description }}</p>
                </div>

                @if(($workspaceProducts ?? collect())->count() > 1)
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($workspaceProducts as $workspaceProduct)
                            <a
                                href="{{ route('automotive.admin.dashboard', ['workspace_product' => $workspaceProduct['product_code']]) }}"
                                class="btn {{ ($focusedWorkspaceProduct['product_code'] ?? null) === $workspaceProduct['product_code'] ? 'btn-primary' : 'btn-outline-white' }}"
                            >
                                {{ $workspaceProduct['product_name'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="row">
                <div class="col-xl-8 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Module Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="text-muted small mb-1">Focused Product</div>
                                <h5 class="mb-1">{{ $focusedWorkspaceProduct['product_name'] ?? 'Workspace Product' }}</h5>
                                <p class="mb-0 text-muted">
                                    {{ $focusedWorkspaceProduct['plan_name'] ?? 'No plan mapped yet' }} ·
                                    {{ !empty($focusedWorkspaceProduct['is_accessible']) ? 'Connected' : ($focusedWorkspaceProduct['status_label'] ?? 'Unavailable') }}
                                </p>
                            </div>

                            @if(!empty($focusedWorkspaceProduct['capabilities']))
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($focusedWorkspaceProduct['capabilities'] as $capabilityName)
                                        <span class="badge bg-primary-subtle text-primary border">{{ $capabilityName }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 d-flex">
                    <div class="card flex-fill">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                @foreach($links as $link)
                                    <a href="{{ route($link['route'], $workspaceQuery) }}" class="btn btn-outline-light text-start">
                                        <i class="isax {{ $link['icon'] }} me-2"></i>{{ $link['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @include('automotive.admin.components.page-footer')
        </div>
    </div>
@endsection
