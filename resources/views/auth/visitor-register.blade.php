@extends('layouts.focus_minimenu', [
    'banner' => 'Register your identity on this Client',
    'help_title' => 'About Identities',
    'help_link' => null,
])

@section('content')
    <div class="h2">Registration</div>
    <div id="appIdentityUpload">

        <div class="bg-danger rounded text-white p-2 mb-4">Warning! Avoid storing your private seed in an untrusted (not
            owned) Serveronet client</div>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <?php
        $registerRoute = domainRoute('register', ['site_id' => \App\Http\Consts::visitorControlPanelAddress, 'target_site_id' => $site_id]);
        ?>

        <form method="POST" action="{{ $registerRoute }}">
            @csrf

            <!-- Visitor Id -->
            <div class="mt-4">
                <x-label for="visitor_id" :value="'Identitiy was generated for you. Paste yours or upload if already owned an Identity.'" />
                <br>
                <br>
                <span class="text-gray-500">Visitor</span>
                <div class="input-group">

                    <input type="text" id="visitor_id" name="visitor_id" readonly disabled
                        value="{{ old('visitor_id', $visitor_id) }}" v-model="visitor_id"
                        class="form-control form-control-sm" required>

                </div>
            </div>

            <!-- Seed -->
            <div class="mt-4">
                <span class="text-gray-500">Seed (base64)</span>
                <div class="input-group">
                    <input id="base64_seed" v-model="base64_seed" class="form-control" type="text" name="base64_seed"
                        value="{{ old('base64_seed', $keySet->base64_seed) }}" @input="onBase64SeedChange" required />
                    <label class="btn btn-outline-dark">
                        <input type="file" class="hidden" id="file_picker" name="file_picker" @change="handleFileUpload"
                            accept=".json,.html,.htm" />
                        ...
                    </label>
                </div>

            </div>

            <!-- Alias  -->
            <div class="mt-4">
                <!-- <x-label for="alias" :value="'Alias'" /> -->
                <span class="text-gray-500">Label (Optional)</span>
                <input id="alias" v-model="alias" class="form-control" type="text" name="alias"
                    value="{{ old('alias', $alias) }}" />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-label for="password" :value="__('Password')" />
                <br>
                <span class="text-gray-500">Set your password to login to sites. It is valid for <b
                        class="text-warning">this client only</b>.</span>
                <input id="password" class="form-control" type="password" name="password" required
                    value="{{ $password }}" autocomplete="new-password" />
            </div>

            <!-- Confirm Password -->
            <div class="mt-4">
                <x-label for="password_confirmation" :value="__('Confirm Password')" />

                <input id="password_confirmation" class="form-control" type="password" value="{{ $password }}"
                    name="password_confirmation" required />
            </div>

            <div class="flex items-center justify-end mt-4">
                <?php
                $loginRoute = domainRoute('login', ['site_id' => $site_id]);
                ?>
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ $loginRoute }}">
                    Already registered on this client?
                </a>

                <x-button class="ml-3">
                    {{ __('Register') }}
                </x-button>

            </div>
        </form>
        <script>
            new_visitor_id = "{{ $visitor_id }}"

            new_base64_seed = "{{ $keySet->base64_seed }}"
        </script>
    </div>
@endsection
