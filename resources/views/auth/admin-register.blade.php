@extends('layouts.guest', ['banner' => 'Register Admin', 'help_title' => 'Client Administration', 'help_link' => null])

@section('content')
    <div class="h3">Administrator Registration</div>
    <div class="h6">Development only</div>
    <div class="">To be hidden on prod release</div>

    <form method="POST" action="{{ route('register_admin') }}">
        @csrf

        <!-- Name -->
        <div>
            <!-- <x-label for="name" :value="__('Name')" /> -->

            <x-input id="name" class="block mt-1 w-full" type="hidden" name="name" :value="'admin'" required
                autofocus />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-label for="password" :value="__('Password')" />

            <x-input id="password" class="form-control" type="password" name="password" required
                autocomplete="new-password" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-input id="password_confirmation" class="form-control"  type="password"
                name="password_confirmation" required />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('admin_login') }}">
                {{ __('Admin Login') }}
            </a>

            <x-button class="ml-3">
                {{ __('Register') }}
            </x-button>
        </div>
    </form>
@endsection
