<div class="flex flex-col" style="height: calc(100vh - 8px); margin: 2px; margin-top: 0;">
    {{-- Single-line toolbar --}}
    <div class="flex items-center gap-2 px-2 py-1 bg-gray-100 border-b border-gray-300 flex-shrink-0">
        <span class="font-bold text-gray-800 text-sm whitespace-nowrap">Logs</span>

        @if($autoRefresh)
            <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-100 px-1.5 py-0.5 rounded-full whitespace-nowrap">
                <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                {{ $refreshInterval }}s
            </span>
        @else
            <span class="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded-full whitespace-nowrap">
                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                Off
            </span>
        @endif

        <label class="inline-flex items-center gap-1 cursor-pointer select-none">
            <input type="checkbox" wire:model.live="autoRefresh" class="w-3 h-3" />
            <span class="text-xs text-gray-600">Auto</span>
        </label>

        <select
            wire:model.live="refreshInterval"
            @if(!$autoRefresh) disabled @endif
            class="border border-gray-300 rounded px-1 py-0.5 text-xs {{ $autoRefresh ? '' : 'opacity-50 cursor-not-allowed' }}"
        >
            <option value="1">1s</option>
            <option value="2">2s</option>
            <option value="5">5s</option>
        </select>

        @if(!$autoRefresh)
            <button
                wire:click="refreshLogs"
                class="bg-blue-500 hover:bg-blue-700 text-white text-xs py-0.5 px-2 rounded inline-flex items-center gap-1"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        @endif

        <div class="flex-1"></div>

        <button
            wire:click="clearLogs"
            wire:confirm="Are you sure you want to clear the logs?"
            class="bg-red-500 hover:bg-red-700 text-white text-xs py-0.5 px-2 rounded"
        >
            Clear
        </button>
    </div>

    {{-- Log container fills remaining space --}}
    <div
        id="log-container"
        class="bg-gray-900 text-green-400 p-2 overflow-auto flex-1 font-mono text-xs whitespace-pre-wrap"
    >
        @if(empty($logContent))
            <span class="text-gray-500 italic">No logs to show.</span>
        @else
            {{ $logContent }}
        @endif
    </div>

    <style>
        #log-container::-webkit-scrollbar {
            width: 6px;
        }
        #log-container::-webkit-scrollbar-track {
            background: #2d3748;
        }
        #log-container::-webkit-scrollbar-thumb {
            background: #4a5568;
            border-radius: 3px;
        }
        #log-container::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }
    </style>

    @script
    <script>
        let pollTimer = null;

        const scrollToBottom = () => {
            const container = document.getElementById('log-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        };

        const startPolling = (intervalSeconds) => {
            stopPolling();
            pollTimer = setInterval(() => {
                $wire.loadLogs();
            }, intervalSeconds * 1000);
        };

        const stopPolling = () => {
            if (pollTimer !== null) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        };

        // Scroll on initial load
        scrollToBottom();

        // Start polling with initial values
        const initialAutoRefresh = $wire.get('autoRefresh');
        const initialInterval = $wire.get('refreshInterval');
        if (initialAutoRefresh) {
            startPolling(initialInterval);
        }

        // React to property changes
        Livewire.hook('morph.updated', ({ component }) => {
            if (component.name === 'log-viewer') {
                const autoRefresh = $wire.get('autoRefresh');
                const interval = $wire.get('refreshInterval');

                if (autoRefresh) {
                    startPolling(interval);
                    requestAnimationFrame(scrollToBottom);
                } else {
                    stopPolling();
                }
            }
        });

        // Scroll after clearLogs dispatches logs-updated
        $wire.on('logs-updated', () => {
            setTimeout(scrollToBottom, 50);
        });

        // Scroll after manual refresh
        $wire.on('logs-refreshed', () => {
            setTimeout(scrollToBottom, 50);
        });
    </script>
    @endscript
</div>
