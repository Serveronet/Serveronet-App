<div class="progress" role="progressbar" aria-label="Animated striped example" x-data="{ in_progress: false }"
    x-show="in_progress"
    x-on:show-popup.window="
  extra = $event.__livewire.params
  popover_title = extra.title ?? null
  popover_content = decodeURI(extra.html_content)
  
  if (popover_title != null)
    toastr.success(popover_content, popover_title, {timeOut: 10000})
  ">
</div>

@include('scripts_end')
@if (session('success') || session('status'))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            toastr.success("{{ session('success') . session('status') }}", {timeOut: 10000});
        });
    </script>
@endif

@if (session('error')))
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            toastr.error("{{ session('error') }}", {timeOut: 10000});
        });
    </script>
@endif


</body>
