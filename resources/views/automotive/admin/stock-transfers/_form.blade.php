@csrf

@php
    $branches = $branches ?? collect();
    $products = $products ?? collect();

    $oldItems = old('items', []);

    if (empty($oldItems)) {
        $oldItems = [
            [
                'product_id' => $stockTransfer->product_id ?? '',
                'quantity' => $stockTransfer->quantity ?? '',
            ]
        ];
    }
@endphp

<div class="row">
    <div class="col-lg-6 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label">{{ __('tenant.from_branch') }} <span class="text-danger">*</span></label>
            <select name="from_branch_id" class="form-control @error('from_branch_id') is-invalid @enderror" required>
                <option value="">{{ __('tenant.select_source_branch') }}</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}"
                        {{ (string) old('from_branch_id', $stockTransfer->from_branch_id ?? '') === (string) $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('from_branch_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-lg-6 col-md-6">
        <div class="form-group mb-3">
            <label class="form-label">{{ __('tenant.to_branch') }} <span class="text-danger">*</span></label>
            <select name="to_branch_id" class="form-control @error('to_branch_id') is-invalid @enderror" required>
                <option value="">{{ __('tenant.select_destination_branch') }}</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}"
                        {{ (string) old('to_branch_id', $stockTransfer->to_branch_id ?? '') === (string) $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            @error('to_branch_id')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="form-group mb-3">
            <label class="form-label">{{ __('tenant.notes') }}</label>
            <textarea
                name="notes"
                rows="3"
                class="form-control @error('notes') is-invalid @enderror"
            >{{ old('notes', $stockTransfer->notes ?? '') }}</textarea>
            @error('notes')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label mb-0">{{ __('tenant.items') }} <span class="text-danger">*</span></label>
        </div>

        @error('items')
        <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="thead-light">
                <tr>
                    <th style="width: 70%;">{{ __('tenant.product') }}</th>
                    <th style="width: 30%;">{{ __('tenant.quantity') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($oldItems as $index => $item)
                    <tr>
                        <td>
                            <select
                                name="items[{{ $index }}][product_id]"
                                class="form-control @error('items.'.$index.'.product_id') is-invalid @enderror"
                                required
                            >
                                <option value="">{{ __('tenant.select_product') }}</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}"
                                        {{ (string) ($item['product_id'] ?? '') === (string) $product->id ? 'selected' : '' }}>
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('items.'.$index.'.product_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </td>
                        <td>
                            <input
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="items[{{ $index }}][quantity]"
                                value="{{ $item['quantity'] ?? '' }}"
                                class="form-control @error('items.'.$index.'.quantity') is-invalid @enderror"
                                required
                            >
                            @error('items.'.$index.'.quantity')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <small class="text-muted">
            {{ __('tenant.stock_transfer_form_hint') }}
        </small>
    </div>

    <div class="col-12 mt-3">
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="isax isax-save-2 me-1"></i> {{ __('tenant.save_draft') }}
            </button>

            <a href="{{ route('automotive.admin.stock-transfers.index') }}" class="btn btn-light">
                {{ __('tenant.cancel') }}
            </a>
        </div>
    </div>
</div>
