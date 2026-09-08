@extends('layouts.focus_minimenu', ['banner' => 'Retrieving', 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
<div id="appRetrieval">
    <div class="mt-auto">
        <img id="hub" :class="{ 'spinning': isSpinning }" src="{{ asset('sn_client_resources/img/hub_FILL0_wght100_GRAD-25_opsz48.svg', request()->isSecure()) }}" />
        <span class="h3 ms-2" v-show="retriesCount != maxRetries">Connecting<span>@{{ marker }}</span></span>
        </div>

        <div class="h6">
            <span class="fw-bold">Site Id: </span>
            <span class="fw-normal">{{ $site_id }}</span>
            <div class="h6">
            </div>
            <span class="fw-bold">Retrieving:</span>
            <span class="fw-normal">
                {{ $missing }} {{ $res_id ?? '' }}
            </span>
        </div>
        <div class="h6">
            <span class="fw-bold">
                State:
            </span>
            <span class="fw-normal">
                @{{ state }}
            </span>
        </div>
        <div class="h6 text-dark bg-warning shadow me-1 mb-2 p-1" v-show="retriesCount == maxRetries">@{{ error_state }}</div>
        <div><a class="btn btn-lg btn-outline-success mt-3" href="{{ $target }}">Retry Now</a></div>
        <br>
        <br>
        <div class="accordion accordion-flush" id="accordionFlushExample">
            <div class="accordion-item">
                <h2 class="accordion-header" id="flush-headingOne">
                    <button class="accordion-button collapsed text-light" type="button" data-bs-toggle="collapse"
                        data-bs-target="#flush-collapseOne" aria-expanded="true" aria-controls="flush-collapseOne">
                        Debug data
                    </button>
                </h2>
                <div id="flush-collapseOne" class="accordion-collapse collapse" aria-labelledby="flush-headingOne"
                    data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body">
                        <div class="h6">
                            <span class="fw-bold">
                                Debug data:
                            </span>
                            <span class="fw-normal">
                                @{{ state_debug }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            target = '{{ $target }}'
        </script>

    </div>
@endsection
