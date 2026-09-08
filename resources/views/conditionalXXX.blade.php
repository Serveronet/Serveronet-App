@extends('layouts.focus_minimenu', ['banner' => $status_code, 'help_tag' => \App\Dicts\DocsMapping::about_serveronet_sites])

@section('content')
    <div class="d-flex align-items-center">
        <div class="h1 fw-bold">{{ $status_code }} </div>
        <div class="h1 ms-1 me-1"> | </div>
        <div class="h2"> {{ \Symfony\Component\HttpFoundation\Response::$statusTexts[$status_code] }}</div>
    </div>
    <hr>
    <div class="fs-5">{{ $message }}</div>
    <br>
    <br>
    @isset($debug_data)
        <div class="accordion accordion-flush" id="accordionFlushExample">
            <div class="accordion-item">
                <h2 class="accordion-header" id="flush-headingOne">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">
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
                                {{ $debug_data }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endisset
@endsection
