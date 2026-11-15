<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Odkleni' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-cream">
    <main class="flex min-h-screen items-center justify-center p-6">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>
