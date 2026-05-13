<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div class="input-group" style="max-width: 360px;">
                <span class="input-group-text bg-white border-end-0">
                    <i class="isax isax-search-normal fs-12"></i>
                </span>
                <input type="text" class="form-control border-start-0 ps-0 bg-white" placeholder="{{ __('access.search_permissions') }}" data-permission-search>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                @productCan('automotive_service.access.roles.manage', 'automotive_service')
                    <button type="button" class="btn btn-outline-white btn-sm" data-preset="read">{{ __('access.preset_read_only') }}</button>
                    <button type="button" class="btn btn-outline-white btn-sm" data-preset="manager">{{ __('access.preset_manager') }}</button>
                    <button type="button" class="btn btn-outline-white btn-sm" data-preset="full">{{ __('access.preset_full_access') }}</button>
                    <button type="button" class="btn btn-outline-white btn-sm" data-select-all>{{ __('access.select_all_permissions') }}</button>
                    <button type="button" class="btn btn-outline-white btn-sm" data-clear-all>{{ __('access.clear_all_permissions') }}</button>
                @else
                    <span class="badge bg-light text-muted border">{{ __('access.read_only') }}</span>
                @endproductCan
            </div>
        </div>

        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="isax isax-danger mt-1"></i>
            <div>{{ __('access.dangerous_permissions_warning') }}</div>
        </div>

        <div class="accordion" id="permissionMatrixAccordion">
            @foreach($groupedPermissions as $group)
                @include('automotive.admin.access.roles.partials._permission-module-card', [
                    'group' => $group,
                    'selectedPermissionKeys' => $selectedPermissionKeys,
                    'index' => $loop->index,
                ])
            @endforeach
        </div>
    </div>
</div>
