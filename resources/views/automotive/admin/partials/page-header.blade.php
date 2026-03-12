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
        @if($actions)
            {{ $actions }}
        @endif
    </div>
</div>

@if(!empty($breadcrumbs))
    <div class="row mb-3">
        <div class="col-12">
            <ul class="breadcrumb">
                @foreach($breadcrumbs as $breadcrumb)
                    @if(!empty($breadcrumb['url']))
                        <li class="breadcrumb-item">
                            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                        </li>
                    @else
                        <li class="breadcrumb-item active">{{ $breadcrumb['label'] }}</li>
                    @endif
                @endforeach
            </ul>
        </div>
    </div>
@endif
