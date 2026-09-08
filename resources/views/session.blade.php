@if (session('status'))
    <div class="alert alert-success overflow-auto">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger overflow-auto">
        {{ session('error') }}
    </div>
@endif
