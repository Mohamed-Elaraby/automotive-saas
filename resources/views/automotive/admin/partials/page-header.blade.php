@props([
'title',
'subtitle' => null,
'breadcrumbs' => [],
'actions' => null,
])

<div class="page-header">
    <div class="content-page-header">
        <h5>{{ $title }}</h5>

        @if($subtitle)
            <p class="text-muted mb-0">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="list-btn">
        @if(!empty($actions))
            @if(is_string($actions))
                {!! $actions !!}
            @elseif(is_array($actions))
                <div class="d-flex gap-2 flex-wrap">
                    @foreach($actions as $action)
                        @php
                            $actionLabel = $action['label'] ?? 'Action';
                            $actionUrl = $action['url'] ?? 'javascript:void(0);';
                            $actionClass = $action['class'] ?? 'btn btn-primary';
                            $actionIcon = $action['icon'] ?? null;
                        @endphp

                        <a href="{{ $actionUrl }}" class="{{ $actionClass }}">
                            @if($actionIcon)
                                <i class="{{ $actionIcon }} me-1"></i>
                            @endif
                            {{ $actionLabel }}
                        </a>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>

@if(!empty($breadcrumbs))
    <div class="row mb-3">
        <div class="col-12">
            <ul class="breadcrumb">
                @foreach($breadcrumbs as $breadcrumb)
                    @php
                        $label = is_array($breadcrumb) ? ($breadcrumb['label'] ?? '') : (string) $breadcrumb;
                        $url = is_array($breadcrumb) ? ($breadcrumb['url'] ?? null) : null;
                    @endphp

                    @if($url)
                        <li class="breadcrumb-item">
                            <a href="{{ $url }}">{{ $label }}</a>
                        </li>
                    @else
                        <li class="breadcrumb-item active">{{ $label }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
@endif
