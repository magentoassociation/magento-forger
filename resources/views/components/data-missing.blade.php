@props(['title' => 'No data yet'])

<div class="bg-yellow-100 border border-yellow-300 text-yellow-800 p-4 rounded-xl mb-8" role="alert">
    <h4 class="text-lg font-medium mb-1">{{ $title }}</h4>
    <p class="mb-0">
        {{ $slot->isEmpty()
            ? 'The OpenSearch indices are empty or missing. Run the sync commands to populate them.'
            : $slot }}
    </p>
</div>