@if ($errors ?? [])
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endif

@if (session('error_site_specific') && isset($site_id) && $site_id == session('site_id'))
    <div class="alert alert-danger">
        {{ session('error_site_specific') }}
    </div>
@endif
