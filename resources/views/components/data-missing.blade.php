@props(['title' => 'No data yet'])

<div class="alert alert-warning" role="alert">
    <h4 class="alert-heading fs-6 fw-medium mb-1">{{ $title }}</h4>
    <p class="mb-0">
        {{ $slot->isEmpty()
            ? 'The OpenSearch indices are empty or missing. Run the sync commands to populate them.'
            : $slot }}
    </p>
</div>