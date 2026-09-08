<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Manager</title>
    <script src="/sn_client_resources/js/tailwind.3.4.17.js"></script>
    @livewireStyles
</head>

<body class="min-h-screen m-0 p-0">
    @livewire('log-viewer')
    @livewireScripts
</body>

</html>
