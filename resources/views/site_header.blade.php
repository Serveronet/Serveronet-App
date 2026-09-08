<div class="overflow-auto">
    <div class="progress" role="progressbar" aria-label="Animated striped example" x-data="{ in_progress: false }"
        x-show="in_progress" x-on:action-start.window="in_progress = true" x-on:action-end.window="in_progress = false"
        x-on:action-failed.window="
          error_message = $event.__livewire.params[0].error_message
          data = $event.__livewire.params[0].data
          debug_data = $event.__livewire.params[0].debug_data
          popover_title = error_message
          popover_content = data
          popover_debug_data = debug_data

          if (popover_title != null) {
            toastr.success(popover_content, popover_title, {timeOut: 10000})
          } 
        "
        x-transition aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="height: 8px;">
        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info 
        @{{ update_result == '❌' ? 'bg-danger' : '' }}"
            style="width: 100%"></div>
    </div>

    <a class="h5 ms-2" target="_blank"
        href="{{ \App\Http\H::a(\App\Http\H::siteUrl($site_id)) }}">{{ $title }}</a>&nbsp;<span>{{ $site_id }}</span>

</div>
