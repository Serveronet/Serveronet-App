@extends('layouts.app', ['banner' => 'Advanced Control Panel', 'help_tag' => App\Dicts\DocsMapping::client_administration])

@section('content')
    <br>
    <a name="log"></a>
    <div class="h4">View logs</div>
    <a class="btn btn-outline-secondary" href="{{ domainRoute('manage_log') }}" target="_blank">View logs</a>
    <br>
    <br>
    <hr>
    <br>
    <div class="h4">Download log</div>

    <form action="{{ 'debug_download_log' }}" method="POST">
        @csrf
        <button class="btn btn-outline-secondary" type="submit" method="POST">Download</button>
    </form>
    <br>
    <hr>
    <br>
    <a name="modify_symlink"></a>
    <div class="h4">Site Development - Local public folder</div>
    <span class="fs-6">Preview Developed site on your local client. </span>
    <br>
    <span class="fs-6">Warning: Might be insecure. Files on your drive are exposed and rendered.</span>
    <form class="mb-4 mt-1" method="POST">
        @csrf
        <div>Type this phrase to acknowledge security concerns</div>
        <div class="fst-italic">{{ $requiredPhrase }}</div>
        <input type="text" class="form-control mt-1 mb-1" name="required_phrase" placeholder="Type required phrase here">
        @if ($symlinkExists)
            <button type="submit" formaction="{{ domainRoute('modify_symlink', ['create' => false]) }}"
                class="btn btn-outline-danger mb-1" title="Remove symlink to preview Sites locally">Remove
                Symlink</button>
        @else
            <button type="submit" formaction="{{ domainRoute('modify_symlink', ['create' => true]) }}"
                class="btn btn-outline-success mb-1" title="Create symlink to preview Sites locally">Create
                Symlink</button>
        @endif
        <br>
        <span>Symlink exists: {{ $symlinkExists ? '✓' : '⧠' }}</span> |
        <span>Autocreate symlink: <a class="text-decoration-none"
                href="{{ domainRoute('settings') }}">{{ $autoCreateSymlink ? '✓' : '⧠' }}</a></span>
    </form>
    <br>
    <hr>
    <br>

    <div class="h4">Update this client</div>
    <a href="{{ domainRoute('update_client_from_central', [
        'source' => $current_source,
        'andUpdate' => true,
        'channel' => $current_channel,
        'forceUpdate' => true,
        'pfm' => true,
    ]) }}"
        class="btn btn-danger }} m-1">Update</a>
    <br>


    <br>
    <hr>
    <br>
    <a name="toggle_control_panel_property"></a>
    <div class="h3">Private or Public Client</div>
    <div class="h6">Serveronet Sites browsing available to all visitors or Client Owner only</div>
    <div class="mb-1">Currently: <span class="h6 fw-bold">{{ $ownerOnly ? 'Owner\'s only' : 'Public for visitors' }}<span>
    </div>
    <form action="{{ 'toggle_control_panel_property' }}" method="POST">
        @csrf
        <input type="hidden" name="property" value="owner_only" />
        <button class="btn btn-outline-secondary" type="submit">Toggle</button>
    </form>
    <br>
    <hr>
    <br>

    <div class="h4">Client actions</div>

    <br>
    @foreach ($tags as $tag)
        <form action="{{ domainRoute('execute_client_action', ['tag' => $tag, 'pfm' => true]) }}" method="POST">
            @csrf
            <button class="btn btn-outline-secondary form-control" type="submit" method="POST">
                {{ $tag }}</button>
        </form>
        <br>
    @endforeach

    <br>
    <hr>
    <br>
    <div class="h4">Update your client using specified update channel</div>
    @foreach ($updateVariants as $key => $variant)
        <a href="{{ domainRoute('update_client_from_central', [
            'source' => $variant['source'],
            'andUpdate' => $variant['andUpdate'],
            'channel' => $variant['channel'],
            'forceUpdate' => true,
            'pfm' => true,
        ]) }}"
            class="btn btn-{{ $variant['css'] }} m-1 form-control">Source Server: {{ $variant['source'] }} | Channel: {{ $variant['channel'] }}</a>
        <br>
    @endforeach
    <br>
    <br>
@endsection
