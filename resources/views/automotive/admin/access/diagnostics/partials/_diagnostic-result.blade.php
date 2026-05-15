@php
    $final = $result['final'] ?? ['allowed' => false, 'reason_code' => 'unknown', 'message' => 'No diagnostic result available.'];
    $badgeClass = ($final['allowed'] ?? false) ? 'bg-success' : 'bg-danger';
@endphp

<div class="row g-3">
    <div class="col-xl-4 col-md-6 d-flex">
        <div class="card flex-fill">
            <div class="card-body">
                <span class="badge {{ $badgeClass }} mb-2">{{ ($final['allowed'] ?? false) ? 'Allowed' : 'Denied' }}</span>
                <h5 class="mb-1">Final Decision</h5>
                <p class="mb-1 text-muted">{{ $final['message'] ?? '-' }}</p>
                <span class="badge bg-light text-dark">{{ $final['reason_code'] ?? 'unknown' }}</span>
            </div>
        </div>
    </div>

    @foreach(['route' => 'Route', 'subscription' => 'Subscription', 'product' => 'Product', 'product_access' => 'Product Access', 'branch' => 'Branch Access', 'roles' => 'Roles', 'permission' => 'Permission', 'owner_access' => 'Owner Access'] as $key => $label)
        @if(isset($result[$key]))
            <div class="col-xl-4 col-md-6 d-flex">
                <div class="card flex-fill">
                    <div class="card-body">
                        <h6 class="mb-2">{{ $label }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    @foreach($result[$key] as $itemKey => $itemValue)
                                        <tr>
                                            <td class="text-muted">{{ str_replace('_', ' ', ucfirst((string) $itemKey)) }}</td>
                                            <td class="text-end">
                                                @if(is_bool($itemValue))
                                                    <span class="badge {{ $itemValue ? 'bg-success' : 'bg-danger' }}">{{ $itemValue ? 'Yes' : 'No' }}</span>
                                                @elseif(is_array($itemValue))
                                                    <code>{{ json_encode($itemValue, JSON_UNESCAPED_SLASHES) }}</code>
                                                @else
                                                    {{ $itemValue ?? '-' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    @if(!empty($result['products']))
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Product Diagnostics</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive table-nowrap">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Subscription</th>
                                    <th>Product Access</th>
                                    <th>Decision</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($result['products'] as $productResult)
                                    <tr>
                                        <td>{{ $productResult['product_key'] ?? '-' }}</td>
                                        <td>{{ $productResult['subscription']['status'] ?? '-' }}</td>
                                        <td>{{ $productResult['product_access']['status'] ?? (($productResult['owner_access']['implicit'] ?? false) ? 'implicit_owner' : '-') }}</td>
                                        <td>
                                            <span class="badge {{ ($productResult['final']['allowed'] ?? false) ? 'bg-success' : 'bg-danger' }}">
                                                {{ ($productResult['final']['allowed'] ?? false) ? 'Allowed' : 'Denied' }}
                                            </span>
                                        </td>
                                        <td><span class="badge bg-light text-dark">{{ $productResult['final']['reason_code'] ?? 'unknown' }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($final['suggested_fix']))
        <div class="col-12">
            <div class="alert alert-warning d-flex align-items-start">
                <i class="isax isax-info-circle me-2 mt-1"></i>
                <div>
                    <h6 class="mb-1">Suggested Fix</h6>
                    <p class="mb-0">{{ $final['suggested_fix'] }}</p>
                </div>
            </div>
        </div>
    @endif
</div>
