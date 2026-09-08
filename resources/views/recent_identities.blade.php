@extends('layouts.focus_minimenu', ['banner' => 'Recent Identities', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <div id="appRecentIdentityControlPanel"></div>
    <script>
        existingRecentVisitorsFromCookie = {!! $existingRecentVisitorsFromCookie !!}
        uiDomains = {!! $uiDomainsJson !!}
    </script>
    <div class="h5">
        Recent identities management
    </div>
    @if (sizeof($existingRecentVisitorsFromCookie) > 0)
        <br>
        <br>
    @endif
    <div class=" mb-1">
        @foreach ($existingRecentVisitorsFromCookie as $recentVisitor)
            <div class="p-2 d-flex me-1 mb-2" style="background-color: {{ $recentVisitor->color }}; color: white;"
                title="{{ $recentVisitor->visitor_id }}">
                <form method="POST" onsubmit="return confirm('Are you sure you want to remove from Recents?')"
                    action="{{ domainRoute('remove_recent_visitor_id', [
                        'site_id' => \App\Http\Consts::visitorControlPanelAddress,
                        'recent_visitor_id_to_be_removed' => $recentVisitor->visitor_id ?? '',
                    ]) }}">
                    @csrf
                    <button class="me-2 badge rounded-pill text-bg-secondary" type="submit" title="Forget">X</button>
                </form>
                <span class="fw-bold me-1 text-shadow text-white">{{ $recentVisitor->alias }}</span>
                <span class="fw-bold me-1">
                    {{ $recentVisitor->visitor_id }}
                    ({{ $recentVisitor->short }})
                </span>
            </div>
        @endforeach
    </div>
@endsection
