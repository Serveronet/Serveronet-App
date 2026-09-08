@extends('layouts.central', ['banner' => 'Demo deployments',
'help_tag' => App\Dicts\DocsMapping::about_serveronet,
])

@section('content')

<ul class="mt-4 list-unstyled">
    @foreach ($demos as $demo)
    <li class="m-2 p-4 mt-3 border border-light rounded bg-dark overflow-auto">
        <a class="h5 text-decoration-none" href="{{$demo['protocol']}}{{$demo['link']}}{{$demo['highlight']}}" target="_blank">
            <span class="link-secondary opacity-75">{{$demo['protocol']}}</span><span class="link-info">{{$demo['link']}}</span><span class="link-primary">{{$demo['highlight']}}</span>
        </a>
        <div class="mt-3 mb-1">
            <span class="h6 fw-bold text-light">{{$demo['title']}}</span> 
            <span class="h6 text-light"> - {{$demo['description']}}</span>
        </div>
    </li>
    @endforeach
</ul>
@endsection