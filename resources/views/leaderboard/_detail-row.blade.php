@if ($row->url)
    <a href="{{ $row->url }}" target="_blank" rel="noopener" class="lb-d-row">
        <span class="lb-d-row-title">{{ $row->title }}</span>
        <span class="lb-d-row-date">{{ $row->date?->format('j M Y') }}</span>
        @include('leaderboard._detail-points', ['row' => $row])
    </a>
@else
    <div class="lb-d-row">
        <span class="lb-d-row-title">{{ $row->title }}</span>
        <span class="lb-d-row-date">{{ $row->date?->format('j M Y') }}</span>
        @include('leaderboard._detail-points', ['row' => $row])
    </div>
@endif
