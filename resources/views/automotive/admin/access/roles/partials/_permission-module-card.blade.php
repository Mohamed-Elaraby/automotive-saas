@php($moduleId = 'permission-module-' . str_replace(['.', '_'], '-', $group['module_key']))

<div class="accordion-item" data-permission-module-card>
    <h2 class="accordion-header" id="{{ $moduleId }}-heading">
        <button class="accordion-button {{ $index > 0 ? 'collapsed bg-light' : '' }} text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $moduleId }}" aria-expanded="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="{{ $moduleId }}">
            <span class="fs-18 fw-bold">{{ $group['module'] }}</span>
            <span class="badge bg-light text-dark border ms-2">{{ $group['permissions']->count() }}</span>
        </button>
    </h2>
    <div id="{{ $moduleId }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" aria-labelledby="{{ $moduleId }}-heading" data-bs-parent="#permissionMatrixAccordion">
        <div class="accordion-body">
            <div class="d-flex justify-content-end gap-2 mb-2">
                <button type="button" class="btn btn-outline-white btn-sm" data-select-module="{{ $group['module_key'] }}">{{ __('access.select_module') }}</button>
                <button type="button" class="btn btn-outline-white btn-sm" data-clear-module="{{ $group['module_key'] }}">{{ __('access.clear_module') }}</button>
            </div>
            <div class="table-responsive table-nowrap">
                <table class="table border mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="w-50">{{ __('access.permission') }}</th>
                            <th>{{ __('access.action') }}</th>
                            <th>{{ __('access.key') }}</th>
                            <th>{{ __('access.allow') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['permissions'] as $permission)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $permission['name'] }}</div>
                                    @if($permission['dangerous'])
                                        <span class="badge bg-warning-transparent text-warning border">
                                            <i class="isax isax-danger me-1"></i>{{ __('access.dangerous') }}
                                        </span>
                                    @endif
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $permission['action'] }}</span></td>
                                <td><code>{{ $permission['key'] }}</code></td>
                                <td>
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission['key'] }}"
                                            data-permission-checkbox
                                            data-module="{{ $group['module_key'] }}"
                                            data-action="{{ $permission['action'] }}"
                                            @checked(in_array($permission['key'], old('permissions', $selectedPermissionKeys), true))
                                        >
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
