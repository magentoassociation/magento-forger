@props(['info'])

<div class="info-text">
    <h2 class="info-text-title">{{ $info->title }}</h2>
    @foreach ($info->paragraphs as $paragraph)
        <p class="info-text-p">{{ $paragraph }}</p>
    @endforeach
</div>
