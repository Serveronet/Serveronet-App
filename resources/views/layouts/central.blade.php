<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('meta', ['title' => $banner ?? 'Serveronet'])
    @include('styles')
    @include('favicon')
    @include('scripts')
</head>
@include('body_begin')
@include('central_menu')
@include('container_begin',['banner' => $banner ?? 'Serveronet'])
@yield('content')
@include('container_end')
@include('body_end')

</html>