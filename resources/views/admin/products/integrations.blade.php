<?php $page = 'products-integrations'; ?>
@extends('admin.layouts.centralLayout.mainlayout')

@section('content')
    <div class="page-wrapper">
        <div class="content">
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="content-page-header">
                    <h5>Integration Builder</h5>
                    <p class="text-muted mb-0">Define how <strong>{{ $product->name }}</strong> should link to other products inside the shared workspace.</p>
                </div>

                <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Back to Product Builder</a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.products.integrations.update', $product) }}">
                @csrf
                @method('PUT')

                <div class="card">
                    <div class="card-body">
                        <div class="alert alert-info">
                            Use this draft to define cross-product navigation and future workspace integrations before wiring them into the runtime manifest.
                        </div>

                        @foreach($integrations as $index => $integration)
                            <div class="border rounded p-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <h6 class="mb-0">Integration {{ $index + 1 }}</h6>
                                    <span class="badge bg-light text-dark">Draft Row</span>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Integration Key</label>
                                        <input type="text" name="integrations[{{ $index }}][key]" value="{{ old("integrations.{$index}.key", $integration['key']) }}" class="form-control" placeholder="perfume-accounting">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Target Product</label>
                                        <select name="integrations[{{ $index }}][target_product_code]" class="form-select">
                                            <option value="">Select target product</option>
                                            @foreach($availableProducts as $availableProduct)
                                                <option value="{{ $availableProduct->code }}" @selected(old("integrations.{$index}.target_product_code", $integration['target_product_code']) === $availableProduct->code)>
                                                    {{ $availableProduct->name }} ({{ $availableProduct->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Target Route Slug</label>
                                        <input type="text" name="integrations[{{ $index }}][target_route_slug]" value="{{ old("integrations.{$index}.target_route_slug", $integration['target_route_slug']) }}" class="form-control" placeholder="general-ledger">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="integrations[{{ $index }}][title]" value="{{ old("integrations.{$index}.title", $integration['title']) }}" class="form-control" placeholder="Sales can post into accounting">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Target Button Label</label>
                                        <input type="text" name="integrations[{{ $index }}][target_label]" value="{{ old("integrations.{$index}.target_label", $integration['target_label']) }}" class="form-control" placeholder="Open Accounting">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Description</label>
                                        <textarea name="integrations[{{ $index }}][description]" rows="3" class="form-control" placeholder="Describe the business connection between the two systems.">{{ old("integrations.{$index}.description", $integration['description']) }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Events</label>
                                        <textarea name="integrations[{{ $index }}][events]" rows="3" class="form-control" placeholder="work_order.completed&#10;stock_movement.valued">{{ old("integrations.{$index}.events", implode("\n", (array) ($integration['events'] ?? []))) }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Payload Schema</label>
                                        <textarea name="integrations[{{ $index }}][payload_schema]" rows="3" class="form-control" placeholder="work_order_id: integer&#10;total_amount: decimal">{{ old("integrations.{$index}.payload_schema", collect((array) ($integration['payload_schema'] ?? []))->map(fn ($type, $field) => $field.': '.$type)->implode("\n")) }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Source Capabilities</label>
                                        <textarea name="integrations[{{ $index }}][source_capabilities]" rows="3" class="form-control" placeholder="workshop.work_order_completion">{{ old("integrations.{$index}.source_capabilities", implode("\n", (array) ($integration['source_capabilities'] ?? []))) }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Target Capabilities</label>
                                        <textarea name="integrations[{{ $index }}][target_capabilities]" rows="3" class="form-control" placeholder="accounting.journal_posting">{{ old("integrations.{$index}.target_capabilities", implode("\n", (array) ($integration['target_capabilities'] ?? []))) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Integrations</button>
                    <a href="{{ route('admin.products.show', $product) }}" class="btn btn-outline-white">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
