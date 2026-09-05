<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">

    <title inertia>{{ config('app.name', 'Maniratn AI') }}</title>

    <link rel="icon" type="image/svg+xml" href="/favicon_v2.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon_v2-96x96.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/favicon_v2-48x48.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/favicon_v2-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon_v2-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon_v2-16x16.png">
    <link rel="shortcut icon" href="/favicon_v2.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon_v2.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|poppins:300,400,500,600,700" rel="stylesheet" />

    @routes
    @vite(['resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased bg-gray-50 text-gray-900">
    @inertia
</body>

</html>
