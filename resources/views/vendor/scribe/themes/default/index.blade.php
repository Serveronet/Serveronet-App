@php
    use Knuckles\Scribe\Tools\WritingUtils as u;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{!! $metadata['title'] !!}</title>

    <style>
    /* open-sans-regular - latin */
    @font-face {
        font-display: swap;
        font-family: 'Open Sans';
        font-style: normal;
        font-weight: 400;
        src: url('/sn_client_resources/fonts/open-sans-v44-latin-regular.woff2') format('woff2'); /* Chrome 36+, Opera 23+, Firefox 39+, Safari 12+, iOS 10+ */
    }
    </style>

    <link rel="stylesheet" href="{!! $assetPathPrefix !!}css/theme-default.style.css" media="screen">
    <link rel="stylesheet" href="{!! $assetPathPrefix !!}css/theme-default.print.css" media="print">

    <script src="/sn_client_resources/js/lodash.min.js"></script>

    <link rel="stylesheet"
          href="/sn_client_resources/css/obsidian.min.css">
    <script src="/sn_client_resources/js//highlight.min.js"></script>

    <script src="/sn_client_resources/js/jets.min.js"></script>

@if(isset($metadata['example_languages']))
    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
        @foreach($metadata['example_languages'] as $lang)
            body .content .{{ $lang }}-example code { display: none; }
        @endforeach
    </style>
@endif

@if($tryItOut['enabled'] ?? true)
    <script>
        var tryItOutBaseUrl = "{!! $tryItOut['base_url'] ?? $baseUrl !!}";
        var useCsrf = Boolean({!! $tryItOut['use_csrf'] ?? null !!});
        var csrfUrl = "{!! $tryItOut['csrf_url'] ?? null !!}";
    </script>

    <script>
    // Hack: Define the variable dynamically on the client side
    // The library will read this global variable instead of the one in the config
    window.tryItOutBaseUrl = window.location.origin; 
    </script>

    <script src="{{ u::getVersionedAsset($assetPathPrefix.'js/tryitout.js') }}"></script>
@endif

    <script src="{{ u::getVersionedAsset($assetPathPrefix.'js/theme-default.js') }}"></script>

</head>

<body data-languages="{{ json_encode($metadata['example_languages'] ?? []) }}">

@include("scribe::themes.default.sidebar")

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        {!! $intro !!}

        {!! $auth !!}

        @include("scribe::themes.default.groups")

        {!! $append !!}
    </div>
    <div class="dark-box">
        @if(isset($metadata['example_languages']))
            <div class="lang-selector">
                @foreach($metadata['example_languages'] as $name => $lang)
                    @php if (is_numeric($name)) $name = $lang; @endphp
                    <button type="button" class="lang-button" data-language-name="{{$lang}}">{{$name}}</button>
                @endforeach
            </div>
        @endif
    </div>
</div>
</body>
</html>
