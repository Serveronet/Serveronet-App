@if (isset($misconfiguredMssage['type']))
    <div class="alert alert-danger">
        @if ($misconfiguredMssage['type'] == \App\Http\Consts::ui_addresses)
            {{ $misconfiguredMssage['message'] }}
            <br>
            {{ $misconfiguredMssage['extra'] }}
        @endif
    </div>
@endif
