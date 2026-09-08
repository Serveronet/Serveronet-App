<style>
    body {
        @if ($site_id == \App\Http\Consts::visitorControlPanelAddress)
            background: linear-gradient(45deg, #ccc 25%, transparent 25%) -50px 0,
                linear-gradient(135deg, #ccc 25%, transparent 25%) -50px 0,
                linear-gradient(45deg, transparent 75%, #ccc 75%),
                linear-gradient(135deg, transparent 75%, #ccc 75%);
            background-size: 10px 10px;
            width: 100%;
            height: 100vh;
        @else
            background-color: {{ '#' . Str::limit(md5($site_id), 6, '') . '70' }} !important;
        @endif
    }
</style>
