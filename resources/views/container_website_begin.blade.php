<div class="sm:items-center py-0 m-6 sm:pt-0 container">

    <div class="mx-auto py-3 sm:px-6 lg:px-8">
        <div class="flex justify-center sm:justify-start sm:pt-0">
            @include ('logo')
            <div class="ms-3 mt-0 align-middle">
                <h3>{{ $banner ?? 'Serveronet' }}</h3>
                <div class="ml-0">
                    <a target="_blank" href="{{ \App\Http\H::helpLink($help_tag ?? '')['link'] ?? '' }}"
                        class="text-dark dark:text-white pre text-decoration-none small">{{ \App\Http\H::helpLink($help_tag ?? '')['title'] . ' 🗯 ' }}</a>
                </div>
            </div>
        </div>
        @include('session')
        @include('error')
        <div class="mt-3 justify-center">
            <div class="grid justify-center grid-cols-1 md:grid-cols-1">
                <div class="">
                    <div class="">
                        <div class="" style="overflow: auto;">
