<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\File;

class LogViewer extends Component
{
    public string $logContent = '';
    public bool $autoRefresh = true;
    public int $refreshInterval = 2;

    public function mount()
    {
        $this->loadLogs();
    }

    public function loadLogs()
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            $lines = file($logPath);
            $this->logContent = implode('', array_slice($lines, -100));
        } else {
            $this->logContent = 'Log file not found.';
        }
    }

    public function refreshLogs()
    {
        $this->loadLogs();
        $this->dispatch('logs-refreshed');
    }

    public function clearLogs()
    {
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }
        $this->logContent = 'Log cleared.';
        $this->dispatch('logs-updated');
    }

    public function render()
    {
        return view('livewire.log-viewer');
    }
}
