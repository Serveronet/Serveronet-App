@extends('layouts.focus', ['banner' => 'First Run', 'help_tag' => \App\Dicts\DocsMapping::installation_and_deployment])
@section('content')
    <noscript>
        <h1>JavaScript required</h1>
    </noscript>

    <!-- start of first run section -->
    <form method="POST" action="./" id="appCheckMysqlConnection">
        @csrf
        <div class="ms-3">
            <div class="h3">Serveronet Client - Inital Configuration</div>
            <div class="h7">Complete First Run to make this client operational</div>



        </div>
        <!-- Network -->
        <div class="ml-1 m-3 mt-6 bg-white dark:bg-gray-800 overflow-hidden">
            <div class="accordion" id="accordionExample">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingNet">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseNet" aria-expanded="false" aria-controls="collapseNet">

                            <div class="flex items-center">
                                <img style="width: 2em;"
                                    src="{{ asset('sn_client_resources/img/hub_FILL0_wght100_GRAD-25_opsz48.svg', request()->isSecure()) }}" />
                                <div class="ms-3 text-lg leading-7 font-semibold">
                                    <a href="#" class="text-decoration-none text-gray-900 dark:text-white">Network
                                        settings</a>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapseNet" class="accordion-collapse collapse collapsed" aria-labelledby="headingNet"
                        data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                            <div class="mt-4 text-gray-600 dark:text-gray-400 text-sm">
                                <div class="h6">Leave default if unsure. Adjust to your situation. </div>
                                <div class="input-group mb-0 p-1 pb-0">
                                    <span class="input-group-text">🔒</span>
                                    <input type="text" class="form-control" name="client_address_https"
                                        id="client_address_https" placeholder="HTTPS Address"
                                        value="{{ $client_address_https }}">
                                </div>
                                <div class="input-group mb-0 p-1 pb-0">
                                    <span class="input-group-text">🌍</span>
                                    <input type="text" class="form-control" name="client_address" id="client_address"
                                        placeholder="Address" value="{{ $client_address }}">
                                </div>
                                <br>
                                Other advanced configurations:
                                <div id="address_container">

                                    @foreach ($advancedAddressesExamples as $address)
                                        <div class="input-group mb-0">
                                            <span class="">{{ $address }} </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Network End -->

        <!-- Database -->
        <div class="ml-1 m-3 mt-6 bg-white dark:bg-gray-800 overflow-hidden">
            <div class="accordion" id="accordionExample">
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingDb">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseDb" aria-expanded="false" aria-controls="collapseDb">
                            <div class="flex items-center">
                                <img style="width: 2em;"
                                    src="{{ asset('sn_client_resources/img/database_FILL0_wght100_GRAD-25_opsz48.svg', request()->isSecure()) }}" />
                                <div class="ms-3 text-lg leading-7 font-semibold">
                                    <a href="#"
                                        class="text-decoration-none text-gray-900 dark:text-white">Database</a>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapseDb" class="accordion-collapse collapse" aria-labelledby="headingDb"
                        data-bs-parent="#accordionExample">
                        <div class="accordion-body">
                            <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">
                                Serveronet Client can use SQLite or MySql database

                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="radio" name="DB_CONNECTION" id="sqlite"
                                        v-model="db_type.name" value="sqlite">
                                    <label class="form-check-label" for="sqlite">
                                        use SQLite
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="DB_CONNECTION" id="mysql"
                                        v-model="db_type.name" value="mysql">
                                    <label class="form-check-label" for="mysql">
                                        use MySql
                                    </label>
                                </div>

                                <div v-show="db_type.name == 'mysql'">
                                    <div class="input-group mb-0 p-1 pb-0">
                                        <span class="input-group-text">DB Host</span>
                                        <input type="text" class="form-control" name="DB_HOST" id="DB_HOST"
                                            value="{{ $client_address }}" v-model="DB_HOST" placeholder="localhost">
                                    </div>
                                    <div class="input-group mb-0 p-1 pb-0">
                                        <span class="input-group-text">DB Port</span>
                                        <input type="text" class="form-control" name="DB_PORT" id="DB_PORT"
                                            value="{{ $client_address }}" v-model="DB_PORT" placeholder="3306">
                                    </div>
                                    <div class="input-group mb-0 p-1 pb-0">
                                        <span class="input-group-text">DB Name</span>
                                        <input type="text" class="form-control" name="DB_DATABASE" id="DB_DATABASE"
                                            value="{{ $client_address }}" v-model="DB_DATABASE"
                                            placeholder="my_database_name">
                                    </div>
                                    <div class="input-group mb-0 p-1 pb-0">
                                        <span class="input-group-text">DB Username</span>
                                        <input type="text" class="form-control" name="DB_USERNAME" id="DB_USERNAME"
                                            value="{{ $client_address }}" v-model="DB_USERNAME"
                                            placeholder="db_username">
                                    </div>
                                    <div class="input-group mb-0 p-1 pb-0">
                                        <span class="input-group-text">DB Password</span>
                                        <input type="text" class="form-control" name="DB_PASSWORD" id="DB_PASSWORD"
                                            value="{{ $client_address }}" v-model="DB_PASSWORD"
                                            placeholder="***db_password***">
                                    </div>
                                    <div class="input-group mb-0 p-1 pb-0">
                                        <span class="input-group-text">DB Table Prefix</span>
                                        <input type="text" class="form-control" name="DB_TABLE_PREFIX"
                                            id="DB_TABLE_PREFIX" value="{{ $client_address }}" v-model="DB_TABLE_PREFIX"
                                            placeholder="sn_">
                                    </div>
                                    <br>
                                    <div class="form-check">

                                        <input class="form-check-input" type="checkbox" value=""
                                            name="siteDatabasesInMySql" id="siteDatabasesInMySql"
                                            v-model="siteDatabasesInMySql">
                                        <label class="form-check-label" for="siteDatabasesInMySql">
                                            Tenant Site's Databases In MySql
                                        </label>
                                        <div class="alert alert-warning">Warning: Requires CREATE SCHEMA and CREATE USER
                                            privileges</div>
                                    </div>
                                    <br>
                                    <div class="input-group mb-0 p-1 pb-0">

                                        <button class="btn btn-outline-secondary" @click="checkMysqlConnection()"
                                            type="button">
                                            Check DB Connection</button>
                                        <span @click="checkMysqlConnection()" class="btn">@{{ db_checking_state }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Database End -->

        <!-- Admin -->
        <div class="m-3 mt-6 bg-white dark:bg-gray-800 overflow-hidden border">
            <div class="p-6">
                <div class="flex items-center">
                    <img style="width: 3em;"
                        src="{{ asset('sn_client_resources/img/key_FILL0_wght100_GRAD-25_opsz48.svg', request()->isSecure()) }}" />


                    <div class="ms-3 text-lg leading-7 font-semibold">
                        <a class="text-decoration-none text-gray-900 dark:text-white">Client Administrator</a>
                    </div>
                </div>

                <div class="ml-2">
                    <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">
                        Administrator can block Sites and adjust settings of this client
                    </div>
                    <!-- Name -->
                    <div>
                        <!-- <x-label for="name" :value="__('Name')" /> -->

                        <x-input id="name" class="block mt-1 w-full" type="hidden" name="name"
                            :value="'admin'" required autofocus />
                    </div>

                    <!-- Password -->
                    <div class="mt-4">
                        <x-label for="password" :value="__('Password')" />

                        <x-input id="password" class="form-control" v-model="password" type="password" name="password"
                            required autocomplete="new-password" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mt-4">
                        <x-label for="password_confirmation" :value="__('Confirm Password')" />

                        <x-input id="password_confirmation" class="form-control" v-model="password_confirmation"
                            type="password" name="password_confirmation" required />
                    </div>
                    <br>

                </div>
            </div>
        </div>
        <!-- Admin End -->
        <div class="alert alert-danger mb-4 h5" v-show="isDbConnectionCorrect()">Check MySql connection first
        </div>
        <button class="btn btn-success form-control" type="submit" v-bind:disabled="isDbConnectionCorrect()"
            formaction="./complete_first_run">Complete the First Run </button>

        <br>
        <br>

        <div :style="{ visibility: showDevNodeConfig ? 'visible' : 'hidden' }">
            <label class="" for="initialTrackersJson">
                Initial Trackers Json:
            </label>
            <input type="text" class="form-control" name="initialTrackersJson" id="initialTrackersJson"
                placeholder="initialTrackersJson" value="{{ $initialTrackersJson }}">
            <label class="" for="initialTrackersJson">
                Custom Dev Central Server:
            </label>
            <input type="text" class="form-control" name="customCentralServer" id="customCentralServer"
                placeholder="https://example.com/" value="">
            <label class="" for="isDevNode">
                Setup As a Dev Node:
            </label>
        </div>
        <input class="opacity-25" type="checkbox" @click="setPasswordForTest();showDevNodeConfig = true" value=""
            id="isDevNode" @mouseenter="showDevNodeConfig = true" name="isDevNode">
    </form>
    @include('client_root')
@endsection
