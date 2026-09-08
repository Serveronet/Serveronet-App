@extends('layouts.guest', ['banner' => 'Please wait', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet])

@section('content')
    <a class="btn btn-primary" href="./">Please wait. Processing...</a>
    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        var xhttp = new XMLHttpRequest();
        xhttp.open("POST", "./start_main_loop_endpoint", true);
        xhttp.setRequestHeader("X-CSRF-TOKEN", csrfToken);
        xhttp.send();

        async function redir() {
            await new Promise(r => setTimeout(
                function() {
                    window.location.href = "{{ $target }}";
                }, 3000));
        }
        redir()
    </script>
@endsection
