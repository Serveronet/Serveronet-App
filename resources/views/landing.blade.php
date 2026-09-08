<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serveronet</title>
    @include('styles')
    @livewireStyles
</head>

<body class="website" style="background: linear-gradient(90deg, white, rgb(232, 226, 226)); ">
    <div class="mt-3 pb-3 d-flex align-items-end justify-content-center mb-2 shadow-sm">

        <div class=" justify-content-center">

            <a class="ms-0 btn btn-outline-primary btn shadow-sm rounded-1" rel="prefetch" wire:navigate.hover=""
                href="{{ domainRoute('demos') }}">Demo 🌍</a>
            <link rel="prefetch" href="{{ domainRoute('demos') }}">

            <a class="ms-1 btn btn-outline-success btn shadow-sm rounded-1" rel="prefetch" wire:navigate.hover=""
                href="{{ route('documentation', ['doc_tag' => \App\Dicts\DocsMapping::installation_and_deployment]) }}">Install
                ⬇</a>
            <link rel="prefetch"
                href="{{ route('documentation', ['doc_tag' => \App\Dicts\DocsMapping::installation_and_deployment]) }}">

            <a class="ms-1 btn btn-outline-secondary btn shadow-sm rounded-1" rel="prefetch" wire:navigate.hover=""
                href="{{ route('documentation', ['doc_tag' => \App\Dicts\DocsMapping::documentation_index]) }}">Docs
                ❔</a>
            <link rel="prefetch"
                href="{{ route('documentation', ['doc_tag' => \App\Dicts\DocsMapping::documentation_index]) }}">

        </div>

    </div>

    <section class="flex-sect">

        <div class="container-width">
            <div class="d-flex  ms-3 pb-2 mb-5 justify-content-center">
                <div class="d-inline ms-2 me-4 ">
                    <span class="display-2 fw-bold" style="color: #1a5264ff">
                        <img class="mt-2" src="{{ asset('sn_client_resources/img/logo.png', request()->isSecure()) }}"
                            style="width:1em; float: left;" />
                        <span class="ms-2">Serveronet</span>
                    </span>
                    <br>
                    <span class="display-5 fw-bold" style="color: #319abc;">P2P Websites</span>
                </div>
            </div>

            <div class="cards">

                @foreach ($landingTexts as $text)
                    <div class="card {{ $text['color'] ?? 'card-back-black' }}">
                        <div class="card-body">

                            <div class="card-title">{{ $text['title'] }}
                            </div>
                            <div class="card-sub-title">{{ $text['subtitle'] }}
                            </div>
                            <div class="card-desc">{{ $text['description'] }}
                            </div>

                        </div>
                        <div class="img-container d-inline-block w-auto p-2 bg-white">
                            <img src="{{ $text['image'] ?? '' }}" class="bg-white" style="max-width: 50px;">
                            <img src="{{ $text['image2'] ?? '' }}" class="bg-white" style="max-width: 50px;">
                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    </section>

    <div class="container-width">


        <a class="ms-2 mb-2 btn p-2 btn-outline-secondary btn-lg rounded-1" target="_blank"
            href="http://github.com/serveronet">See it on Github
            <img class="me-2" src="{{ asset('sn_client_resources/img/github-black.svg', request()->isSecure()) }}"
                style="width:1.6em; float: left;" />
        </a>
        <br>
        <br>
        <br>
        <div class="h3">Future and Posibilities</div>

        <div class="cards">

            @foreach ($roadMapBlocks as $text)
                <div class="card {{ $text['color'] ?? 'card-back-black' }}">
                    <div class="card-body">

                        <div class="card-title">{{ $text['title'] }}
                        </div>
                        <div class="card-sub-title">{{ $text['subtitle'] }}
                        </div>
                        <div class="card-desc">{{ $text['description'] }}
                        </div>

                    </div>
                    <div class="img-container d-inline-block w-auto p-2 bg-white">
                        <img src="{{ $text['image'] ?? '' }}" class="bg-white" style="max-width: 50px;">
                        <img src="{{ $text['image2'] ?? '' }}" class="bg-white" style="max-width: 50px;">
                    </div>
                </div>
            @endforeach

        </div>
    </div>
    <br>

</body>
<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
    }

    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
    }

    .container-width {
        width: 90%;
        max-width: 1150px;
        margin: 0 auto;
    }

    .flex-sect {

        padding: 50px 0;
    }

    .cards {
        padding: 20px 0;
        display: flex;
        justify-content: space-around;
        flex-flow: wrap;
    }

    .card {
        width: 350px;
        margin-bottom: 30px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.2);
        border-radius: 1px;
        transition: all 0.5s ease;
        font-weight: 100;
        overflow: hidden;
    }

    .card:hover {
        margin-top: -5px;
        box-shadow: 0 20px 30px 0 rgba(0, 0, 0, 0.2);
    }

    .card-body {
        padding: 15px 15px 15px 15px;
    }

    .card-title {
        font-size: 1.6em;
        margin-bottom: 5px;
        color: black;
        text-shadow: 1px 1px 1px white;
    }

    .card-sub-title {
        color: #166a85;
        font-size: 1em;
        margin-bottom: 15px;
    }

    .card-desc {
        font-size: 0.85rem;
        line-height: 17px;
        color: black;
    }

    .img-container {
        width: 200px;
        padding: 10px;
    }

    .card-back-black {
        background: rgb(16, 80, 98);
        background: linear-gradient(300deg, lightgrey, white);
        background: rgb(243, 240, 240);
        background: white;
        background: linear-gradient(148deg, white, lightblue);
        background: linear-gradient(148deg, rgb(243, 240, 240), white);
        background: linear-gradient(148deg, white, rgb(243, 240, 240));
        background: white;
    }

    .card-back-gradient {
        /* background: rgb(39,124,124); */
        /* background: linear-gradient(148deg, rgb(68, 159, 185) 0%, rgba(30,50,50,1) 100%);  */
        /* background: linear-gradient(148deg, white, rgb(237, 233, 233));  */
        /* background: linear-gradient(148deg, white, rgb(243, 240, 240));  */
    }

    @media (max-width: 480px) {
        #i02xp {
            float: none;
            text-align: center;
        }
    }

    .header {
        color: rgb(79, 77, 77);
        text-shadow: 1px 1px 1px lightgrey;
        font-size: 3rem;
    }
</style>

</html>
