@extends('layouts.focus', ['banner' => 'SN', 'help_tag' => \App\Dicts\DocsMapping::installation_and_deployment])

@section('content')
    @if ($issueType === 'other')
        <div class="h5">
            While executing First Run setup following issue was detected.
        </div>
        <br>
        <div>
            <div class="h6">Proposed Solutions:</div>
            <div class="h5">1. Review if required prerequisites are installed</div>
            <div class="h5">2. Go back and review your database configuration</div>
        </div>
    @elseif ($issueType === 'file_permissions')
        <div class="h5">
            While executing First Run setup following file permission issue was detected.
        </div>
        <br>
        <div>
            <div class="h6">Proposed Solution:</div>
            <div class="fw-bold">On Linux</div>
            <div>Adjust permissions by setting ownership of files to Apache server user or user on which server runs</div>
            <div>For example: cd ~/your_serveronet_directory && sudo chown -R www-data:www-data ./</div>
        </div>
    @elseif ($issueType === 'ui_fallback')
        <div class="h5">
            This SN client doesn't serve UI on this address
        </div>
        <br>
        <div>
            <div class="h6">Proposed Solution:</div>
            <div>Add current domain to UI Addresses in Control Panel</div>
            <div>Or</div>
            <div>Edit file ui_addresses.txt located in /storage/app/ directory</div>
            <br>
            <div>After making change please wait 1 minute for cache refresh.</div>
        </div>
    @endif

    <br>
    <br>
    <div class="font-monospace bg-white p-1">
        {{ $message }}
    </div>
    <br>
    <br>
    <a class="btn btn-primary" href="{{ url()->previous() }}">Back</a>
@endsection
