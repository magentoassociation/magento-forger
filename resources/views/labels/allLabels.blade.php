@extends('layouts.app')

@section('content')
    @if ($dataMissing)
        <x-data-missing>
            The issues index is empty or missing. Run
            <code>ddev artisan sync:github:issues</code> to populate it.
        </x-data-missing>
    @endif

    <div class="row">
        @php $columnCount = 0; @endphp
        @if(empty($labels))
            <div class="alert alert-info text-center">
                <h4>There is no data available, please ensure the import has run.</h4>
            </div>
        @else
            @foreach($labels as $prefix => $labelGroup)
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>{{ $prefix === 'no_prefix' ? 'Other Labels' : $prefix }}</span>
                            <small class="text-muted">{{ count($labelGroup) }} labels</small>
                        </div>
                        <ul class="list-group list-group-flush">
                            @php $maxVisible = 8; @endphp
                            @foreach($labelGroup as $index => $labelData)
                                @php
                                    // Wrap the label in quotes, then URL-encode it
                                    $quotedLabel = urlencode('"' . $labelData['label'] . '"');
                                    $githubUrl = "https://github.com/magento/magento2/issues?q=is%3Aissue+is%3Aopen+label%3A{$quotedLabel}";
                                @endphp
                                <li class="list-group-item d-flex justify-content-between align-items-center {{ $index >= $maxVisible ? 'collapse-item d-none' : '' }}" data-group="{{ $loop->parent->index }}">
                                    <a href="{{ $githubUrl }}" target="magentoForgerGitHub" class="text-decoration-none">
                                        {{ $labelData['label'] }}
                                    </a>
                                    <span class="badge bg-primary rounded-pill">{{ $labelData['count'] }}</span>
                                </li>
                            @endforeach
                            @if(count($labelGroup) > $maxVisible)
                                <li class="list-group-item text-center">
                                    <button class="btn btn-sm btn-outline-primary expand-btn" data-group="{{ $loop->index }}" onclick="toggleGroup({{ $loop->index }})">
                                        <i class="fas fa-chevron-down"></i> Show {{ count($labelGroup) - $maxVisible }} more
                                    </button>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>

                @php
                    $columnCount++;
                    if ($columnCount % 3 === 0) {
                        echo '</div><div class="row">';
                    }
                @endphp
            @endforeach
        @endif
    </div>
@endsection

@push('scripts')
<script>
function toggleGroup(groupIndex) {
    const button = document.querySelector(`.expand-btn[data-group="${groupIndex}"]`);
    const hiddenItems = document.querySelectorAll(`.collapse-item[data-group="${groupIndex}"]`);
    const isExpanded = !hiddenItems[0].classList.contains('d-none');
    
    hiddenItems.forEach(item => {
        if (isExpanded) {
            item.classList.add('d-none');
        } else {
            item.classList.remove('d-none');
        }
    });
    
    if (isExpanded) {
        button.innerHTML = `<i class="fas fa-chevron-down"></i> Show ${hiddenItems.length} more`;
    } else {
        button.innerHTML = `<i class="fas fa-chevron-up"></i> Show less`;
    }
}
</script>
@endpush
