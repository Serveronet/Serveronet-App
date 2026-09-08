<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('meta', ['title' => $banner ?? 'Serveronet'])
    @include('styles')
    @include('favicon')
    @include('scripts')
    {{-- @livewireStyles --}}
</head>
@include('body_begin')
@include('address_mismatch_banner', ['is_misconfigured' => $is_misconfigured ?? null])
@include('guest_menu')
@include('container_begin',['banner' => $banner ?? 'Serveronet'])
@yield('content')
@include('container_end')
@include('body_end')

</html>