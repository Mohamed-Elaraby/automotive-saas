@props([
'title' => 'No data found',
'message' => 'There are no records to display.',
])

<div class="card">
    <div class="card-body text-center py-5">
        <div class="mb-2">
            <i class="isax isax-box text-muted" style="font-size: 48px;"></i>
        </div>
        <h5 class="mb-2">{{ $title }}</h5>
        <p class="text-muted mb-0">{{ $message }}</p>
    </div>
</div>
