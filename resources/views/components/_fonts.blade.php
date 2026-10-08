{{-- Self-hosted design fonts (latin subset, OFL): Libre Franklin + Martian Mono.
     Replaces the render-blocking Google Fonts <link> the redesign shipped with.
     Preload only the two hottest weights (README.md "Fonts"). --}}
<link rel="preload" as="font" type="font/woff2" crossorigin href="{{ asset('fonts/libre-franklin-600.woff2') }}">
<link rel="preload" as="font" type="font/woff2" crossorigin href="{{ asset('fonts/martian-mono-500.woff2') }}">
<style>
    @font-face {
        font-family: 'Libre Franklin';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/libre-franklin-400.woff2') }}') format('woff2');
    }
    @font-face {
        font-family: 'Libre Franklin';
        font-style: normal;
        font-weight: 500;
        font-display: swap;
        src: url('{{ asset('fonts/libre-franklin-500.woff2') }}') format('woff2');
    }
    @font-face {
        font-family: 'Libre Franklin';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('{{ asset('fonts/libre-franklin-600.woff2') }}') format('woff2');
    }
    @font-face {
        font-family: 'Libre Franklin';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        src: url('{{ asset('fonts/libre-franklin-700.woff2') }}') format('woff2');
    }
    @font-face {
        font-family: 'Martian Mono';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/martian-mono-400.woff2') }}') format('woff2');
    }
    @font-face {
        font-family: 'Martian Mono';
        font-style: normal;
        font-weight: 500;
        font-display: swap;
        src: url('{{ asset('fonts/martian-mono-500.woff2') }}') format('woff2');
    }
    @font-face {
        font-family: 'Martian Mono';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        src: url('{{ asset('fonts/martian-mono-700.woff2') }}') format('woff2');
    }
</style>
