@extends('layouts.' . (\App\Http\H::isServeronetWelcomeSite() ? 'central' : 'guest'), [
    'banner' => $documentation_title,
    'help_tag' => \App\Dicts\DocsMapping::documentation_index,
])

@section('content')
    @include('documentation_menu', ['mapping' => $mapping])

    <style>
        a {
            text-decoration: none;
            color: #333333;
        }

        .heading-permalink {
            text-decoration: none;
            color: pink;
        }
    </style>

    <div class="mt-12 prose dark:prose-invert">
        {!! $markdown !!}
    </div>
    
@endsection
