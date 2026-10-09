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

            <a class="ms-0 btn btn-outline-primary btn shadow-sm rounded-1"
                href="{{ route('demos') }}">Demos 🌍</a>
            <link rel="prefetch" href="{{ route('demos') }}">

            <a class="ms-1 btn btn-outline-success btn shadow-sm rounded-1"
                href="{{ route('documentation', ['doc_tag' => \App\Dicts\DocsMapping::installation_and_deployment]) }}">Install
                ⬇</a>
            <link rel="prefetch"
                href="{{ route('documentation', ['doc_tag' => \App\Dicts\DocsMapping::installation_and_deployment]) }}">

            <a class="ms-1 btn btn-outline-dark btn shadow-sm rounded-1"
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
        <div class="cards">
            <div>
                <a class="btn btn-outline-primary btn btn-lg shadow-sm rounded-1 mt-2" rel="prefetch" target="_blank"
                    href="http://client.serveronet.org/">Try on a Demo client</a>
                    <span class="ms-1 me-1">or</span>
                
                <a class="btn btn-outline-success btn-lg shadow-sm rounded-1 mt-2"
                    href="{{ route('documentation', ['doc_tag' => \App\Dicts\DocsMapping::installation_and_deployment]) }}">Install your Client</a>
            </div>


        </div>
    </div>
    <br>
    <div class="container-width">
        <br>
        <br>

        <div class="h3">Future and Possibilities</div>

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

    <footer class="landing-footer">
        <div class="container-width d-flex justify-content-center">
            <a class="btn btn-outline-light btn shadow-sm rounded-1 ms-1 me-1" target="_blank"
                rel="noopener noreferrer" href="https://github.com/Serveronet/Serveronet-App">
                <svg class="me-2 footer-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12" />
                </svg>
                GitHub
            </a>
            <a class="btn btn-outline-light btn shadow-sm rounded-1 ms-1 me-1" target="_blank"
                rel="noopener noreferrer" href="https://discord.gg/xTNDkBhhS">
                <svg class="me-2 footer-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z" />
                </svg>
                Discord
            </a>
            <a class="btn btn-outline-light btn shadow-sm rounded-1 ms-1 me-1" target="_blank"
                rel="noopener noreferrer" href="https://www.reddit.com/r/ServeronetP2PWebsites/">
                <svg class="me-2 footer-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 3.314 1.343 6.314 3.515 8.485l-2.286 2.286C.775 23.225 1.097 24 1.738 24H12c6.627 0 12-5.373 12-12S18.627 0 12 0Zm4.388 3.199c1.104 0 1.999.895 1.999 1.999 0 1.105-.895 2-1.999 2-.946 0-1.739-.657-1.947-1.539v.002c-1.147.162-2.032 1.15-2.032 2.341v.007c1.776.067 3.4.567 4.686 1.363.473-.363 1.064-.58 1.707-.58 1.547 0 2.802 1.254 2.802 2.802 0 1.117-.655 2.081-1.601 2.531-.088 3.256-3.637 5.876-7.997 5.876-4.361 0-7.905-2.617-7.998-5.87-.954-.447-1.614-1.415-1.614-2.538 0-1.548 1.255-2.802 2.803-2.802.645 0 1.239.218 1.712.585 1.275-.79 2.881-1.291 4.64-1.365v-.01c0-1.663 1.263-3.034 2.88-3.207.188-.911.993-1.595 1.959-1.595Zm-8.085 8.376c-.784 0-1.459.78-1.506 1.797-.047 1.016.64 1.429 1.426 1.429.786 0 1.371-.369 1.418-1.385.047-1.017-.553-1.841-1.338-1.841Zm7.406 0c-.786 0-1.385.824-1.338 1.841.047 1.017.634 1.385 1.418 1.385.785 0 1.473-.413 1.426-1.429-.046-1.017-.721-1.797-1.506-1.797Zm-3.703 4.013c-.974 0-1.907.048-2.77.135-.147.015-.241.168-.183.305.483 1.154 1.622 1.964 2.953 1.964 1.33 0 2.47-.81 2.953-1.964.057-.137-.037-.29-.184-.305-.863-.087-1.795-.135-2.769-.135Z" />
                </svg>
                Reddit
            </a>
        </div>
    </footer>

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

    .landing-footer {
        background: rgb(16, 80, 98);
        padding: 30px 0;
        margin-top: 40px;
    }

    .footer-icon {
        display: inline-block;
        width: 1.4em;
        height: 1.4em;
        vertical-align: -0.3em;
    }
</style>

</html>
