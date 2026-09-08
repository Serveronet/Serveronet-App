@extends('layouts.app', ['banner' => 'Client Settings', 'help_tag' => \App\Dicts\DocsMapping::settings])

@section('content')
    <div id="appSettings">

        <div class="bg-danger mb-4 rounded text-white p-1 ps-2">Warning Settings are not sanitized. Proceed if you know what
            you are doing.</div>
        Edit and Save to apply.
        <br>
        <div class="working" id="working" v-show="operationInProgress">⌛ Please wait...</div>
        <br>
        <br>
        <div class="flex items-center mb-2">
            <button class="btn btn-outline-primary" @click="reload()" type="button">Reload</button>
            <input class="form-control ms-1" id="search" v-model="searchTerm" placeholder="Filter">
        </div>

        <div class="mt-2 text-gray-600 dark:text-gray-400 text-sm">

            <div class="bg-secondary mb-4 rounded text-white p-1 ps-2" v-if="settingsList.value === []">
                Loading...
            </div>
            <div v-for="setting in filteredList()" :key="setting.setting_id">
                <div v-show="setting.is_advanced == true && showAdvanced || setting.is_advanced == false">
                    <div class="mt-4 fw-bold"> @{{ setting.desc }}</div>
                    <span><code>@{{ setting.setting_id }}</code> @{{ setting.type }} Default: @{{ setting.default_value }}</span>
                    <div class="input-group mb-1">
                        <div class="form-switch form-control" v-if="setting.type == 'boolean'">
                            <input class="form-check-input" type="checkbox" role="switch" id="@{{ setting.setting_id }}"
                                v-model="setting.value">
                        </div>
                        <input v-if="['string', 'datetime'].includes(setting.type)" type="text" class="form-control"
                            :placeholder="'Default: ' + setting.default_value" v-model="setting.value">
                        <input v-if="['integer'].includes(setting.type)" type="number" step="1" class="form-control"
                            :placeholder="'Default: ' + setting.default_value" v-model="setting.value">
                    </div>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary btn-sm" @click="save(setting.setting_id)"
                            type="button">Save</button>
                        <span v-if="setting.result" class="btn btn-outline-primary" id="@{{ setting.setting_id }}"><b
                                title="">@{{ setting.result }}</b></span>
                    </div>
                </div>
            </div>
        </div>
        <br>
        <button class="btn btn-outline-secondary" @click="reload()" type="button">Reload</button>
        <div class="mt-2">
            <label for="showAdvanced" class="me-2">
                Show Advanced Settings
            </label>
            <input id="showAdvanced" class="form-check-input" type="checkbox" v-model="showAdvanced">
        </div>

    </div>


    @include('client_root')
@endsection
