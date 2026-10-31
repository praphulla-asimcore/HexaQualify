<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#F3F1FB" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0A0820" media="(prefers-color-scheme: dark)">
    <title inertia>HexaQualify</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/brand/favicon-180.png">
    <link rel="manifest" href="/manifest.webmanifest">
    {{-- Apply the theme before first paint: manual choice, else prefers-color-scheme. --}}
    <script nonce="{{ Vite::cspNonce() }}">
        (function () {
            var t = null;
            try { t = localStorage.getItem('hq-theme'); } catch (e) {}
            if (t !== 'light' && t !== 'dark') t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    <div class="aurora" aria-hidden="true">
        <div class="aurora__blob aurora__blob--1"></div>
        <div class="aurora__blob aurora__blob--2"></div>
        <div class="aurora__blob aurora__blob--3"></div>
        <div class="aurora__grain"></div>
    </div>
    @inertia
</body>
</html>
