@extends('layouts.app', ['banner' => 'Edit Trackers', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <span class="h6">Separate each Url with a line break</span>
    <span class="h6"> - Please note that <span class="text-decoration-line-through fw-bold">UDP</span> trackers are
        currently not supported.</span>
    <form class="ms-1 mb-4 mt-2" method="POST" action="{{ domainRoute('update_trackers') }}">
        @csrf
        <div class="input-group mb-1">
            <textarea name="trackers_urls" id="" cols="30" rows="10" class="form-control">{{$trackersUrls}}</textarea>
        </div>
        <button class="btn btn-outline-dark form-control" type="submit">Save</button>
    </form>
    <br>
    <form action="{{ domainRoute('get_newtrackon_trackers', ['in_ui' => true]) }}" method="POST">
        @csrf
        <button class="btn btn-outline-success" type="submit">Get New Trackers from NewTrackon</button>
    </form>
    <br>
    <br>

    <livewire:trackers-table />
    
@endsection
