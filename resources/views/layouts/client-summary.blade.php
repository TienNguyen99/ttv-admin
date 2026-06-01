<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'TTV Tổng hợp')</title>
    @stack('styles')
</head>

<body>
    @include('layouts.partials.sidebar')
    @yield('content')
    @stack('scripts')
</body>

</html>
