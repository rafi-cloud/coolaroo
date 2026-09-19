@props([
    'css',
    'suffix',
    'vite' => false,
    'title' => null,
    'description' => null,
    'bodyClass' => null,
])
<!DOCTYPE html>
<html lang="en-AU">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@if ($title){{ $title }} — @endif{{ $suffix }}</title>
<meta name="description" content="{{ $description ?? 'Coolaroo Restaurant & Bistro. Wood-fired pizza, burgers and fresh seafood. Scan the QR code at your table for the menu, or book ahead online.' }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&amp;family=Lora:ital,wght@0,400;1,400;1,500&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/'.$css) }}">
@if ($vite)
@vite('resources/js/app.js')
@endif
</head>
@if ($bodyClass)
<body class="{{ $bodyClass }}">
@else
<body>
@endif

{{ $slot }}

@stack('scripts')
</body>
</html>
