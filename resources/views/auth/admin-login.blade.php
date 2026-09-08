@extends('layouts.guest', ['banner' => 'Client Admin', 'help_title' => 'Client Administration', 'help_link' => null])

@section('content')
    <div class="h4">Client Administrator login</div>
    <div class="h6">Use a password set during the First Run setup</div>
    <br>


    <form method="POST" action="{{ domainRoute('admin_login') }}">

        @csrf

        <!-- Name -->
        <div>
            <x-label for="name" :value="__('Admin username')" />

            <x-input id="name" class="form-control" type="text" name="name" :value="'admin'" readonly required />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-label for="password" :value="__('Password')" />           
            <x-input id="password" class="form-control" type="password" name="password" required value="{{ $password }}"

                autocomplete="current-password" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input checked id="remember_me" type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    name="remember">
                <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-button class="ml-3">
                {{ __('Log in') }}
            </x-button>
        </div>
    </form>
@endsection
