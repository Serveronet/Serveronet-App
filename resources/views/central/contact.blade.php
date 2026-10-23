@extends('layouts.central', ['banner' => 'Contact',
'help_tag' => App\Dicts\DocsMapping::about_serveronet,
])

@section('content')
    @include('partials.contact')
@endsection
