@props([
    'href',
    'title',
    'count' => null,
    'blurb' => null,
    'icon' => null,
    'cta' => null,
])

{{-- Shared by §3 path cards (icon + blurb + cta) and §4 area tiles (compact name + count).
     The count is the only count-bearing element, so when it is unknown the card simply
     renders without a pill — the prose stays grammatical.

     Linking differs by card type: a card with a CTA links only the CTA, while a card
     without one (area tile) is fully clickable via a stretched link. --}}
<div class="d-flex flex-column h-100 bg-white p-4 shadow rounded-xl position-relative"
     style="transition: box-shadow .2s, transform .2s;"
     onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
    <div class="d-flex align-items-start justify-content-between gap-2">
        <h4 class="text-lg font-medium mb-0">
            @if ($icon)<span class="me-1">{{ $icon }}</span>@endif{{ $title }}
        </h4>
        @if (! is_null($count))
            <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis fw-semibold flex-shrink-0">
                {{ number_format($count) }} open
            </span>
        @endif
    </div>

    @if ($blurb)
        <p class="text-gray-600 mt-2 mb-0">{{ $blurb }}</p>
    @endif

    @if ($cta)
        <a href="{{ $href }}" class="text-primary fw-medium mt-3">{{ $cta }} →</a>
    @else
        <a href="{{ $href }}" class="stretched-link" aria-label="{{ $title }}"></a>
    @endif
</div>
