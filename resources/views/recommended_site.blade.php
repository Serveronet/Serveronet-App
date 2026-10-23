<style>
    .sn-recommended-card {
        cursor: pointer;
        transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
    }

    .sn-recommended-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .3);
        filter: brightness(1.08);
    }

    .sn-recommended-card:active {
        transform: translateY(-1px) scale(.98);
        filter: brightness(.92);
    }

    .sn-recommended-title {
        color: #fff;
    }

    .sn-recommended-desc {
        color: rgba(255, 255, 255, .9);
    }

    .sn-recommended-cta {
        color: rgba(255, 255, 255, .7);
    }

    .sn-recommended-card:hover .sn-recommended-title {
        text-decoration: underline;
    }

    .sn-recommended-card:hover .sn-recommended-cta {
        color: #fff;
    }

    .sn-collapse-chev {
        display: inline-block;
        transition: transform .2s ease;
    }

    .sn-collapse-trigger {
        color: inherit;
        transition: color .15s ease;
    }

    .sn-collapse-trigger:hover {
        color: var(--bs-link-color);
    }

    [aria-expanded="true"]>.sn-collapse-chev {
        transform: rotate(90deg);
    }
</style>

@php $is_collapsed = request()->cookie('recommended_sites_collapsed') === '1'; @endphp

<div class="h5 mt-4 mb-2">
    <a class="sn-collapse-trigger text-decoration-none" data-bs-toggle="collapse" href="#discover_more_sites_section" role="button"
        aria-expanded="{{ $is_collapsed ? 'false' : 'true' }}" aria-controls="discover_more_sites_section">
        <span class="sn-collapse-chev">&#9656;</span>Discover more sites
    </a>
</div>
<div class="collapse @unless ($is_collapsed) show @endunless" id="discover_more_sites_section">
    <div class="d-flex flex-nowrap overflow-auto pb-3 gap-3">
    @if ($recommended_sites->isNotEmpty())
        @foreach ($recommended_sites as $recommended_site)
            @php
                $site_hash = md5(e($recommended_site['site_id']));
                $site_color = Str::limit($site_hash, 6, '');
                $marker_hue = 90 + (hexdec(substr($site_hash, 0, 2)) % 70);
                $is_available = $recommended_site['site_id'] !== 'tbd';
                $card_style = 'width: 20rem; background: linear-gradient(160deg, rgba(0,0,0,.25), rgba(0,0,0,.6)), #' . $site_color . '; border-left: 6px solid hsl(' . $marker_hue . ', 45%, 42%);';
            @endphp

            @if ($is_available)
                <a class="sn-recommended-card flex-shrink-0 rounded shadow-sm p-3 d-block text-decoration-none"
                    style="{{ $card_style }}"
                    href="{{ \App\Http\H::siteUrl($recommended_site['site_id']) }}">
            @else
                <div class="sn-recommended-card flex-shrink-0 rounded shadow-sm p-3 opacity-75"
                    style="{{ $card_style }}">
            @endif

            <div class="h4 fw-bold mb-1 sn-recommended-title">{{ $recommended_site['title'] }}</div>
            <div class="small sn-recommended-desc">{{ $recommended_site['description'] }}</div>

            @if ($is_available)
                <span class="small fw-bold sn-recommended-cta">Visit site &rarr;</span>
                </a>
            @else
                <span class="badge rounded-pill text-bg-light mt-2">Coming soon</span>
                </div>
            @endif
        @endforeach
    @endif

    <a class="sn-recommended-card flex-shrink-0 rounded shadow-sm p-3 d-block text-decoration-none text-center"
        style="width: 20rem; background: hsl(130, 30%, 96%); border: 2px dashed hsl(130, 35%, 55%);"
        href="http://serveronet.org/contact" target="_blank" rel="noopener noreferrer">
        <div class="display-4 fw-bold lh-1" style="color: hsl(130, 40%, 40%);">+</div>
        <div class="h6 fw-bold" style="color: hsl(130, 25%, 28%);">Do you have an interesting Site? Add yours</div>
    </a>
    </div>
</div>

<script>
    (function () {
        const section = document.getElementById('discover_more_sites_section');
        if (!section) return;
        const setCookie = (value) => {
            document.cookie = 'recommended_sites_collapsed=' + value +
                '; path=/; max-age=31536000; SameSite=Lax';
        };
        section.addEventListener('shown.bs.collapse', () => setCookie('0'));
        section.addEventListener('hidden.bs.collapse', () => setCookie('1'));
    })();
</script>
