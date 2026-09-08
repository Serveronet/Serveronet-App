<?php

namespace App\Providers;

use App\Dicts\CachePrefixes;
use App\Http\H;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register() {}

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (app()->runningInConsole()) {
            return;
        }

        /* Adjust for long running tasks */
        ini_set('max_execution_time', 160);

        $hasClash = false;

        /* Checks is UI addresses aren't both domain and it's subdomain - scenario not supported */
        if (! H::isServeronetWelcomeSite())
        $hasClash = Cache::remember(CachePrefixes::subdomain_clash, 60, function () {
            $urls = H::getUiAddresses();
            // 1. Extract and normalize hosts (remove ports and protocols)
            $hosts = collect($urls)
                ->map(fn($url) => parse_url($url, PHP_URL_HOST))
                ->filter() // Remove nulls if any URL is invalid
                ->map(fn($host) => strtolower($host)) // Domains are case-insensitive
                ->values()
                ->toArray();

            // 2. Check for subdomain/domain relationships
            $hasClash = false;

            // Sort by length (descending) so we check longer strings (subdomains) first
            usort($hosts, fn($a, $b) => strlen($b) <=> strlen($a));

            $count = count($hosts);
            for ($i = 0; $i < $count; $i++) {
                for ($j = 0; $j < $count; $j++) {
                    if ($i === $j) continue;

                    // Check if Host A is a subdomain of Host B
                    // We append a dot to ensure we match "domain.com" strictly and not "mydomain.com"
                    if (str_ends_with($hosts[$i], '.' . $hosts[$j])) {
                        $hasClash = true;

                        // Optional: Log which specific URLs triggered the alert
                        // $subdomain = $hosts[$i];
                        // $domain = $hosts[$j];

                        // Break loops immediately if you just need a boolean check
                        break 2;
                    }
                }
            }

            return $hasClash;
        });

        // 3. Trigger Alert
        if ($hasClash) {
            $message = 'Incorrect Configuration. UI Addresses cannot contain domain and it\'s subdomain. Edit ui_addresses.txt';
            dd($message);
        }

        /* Security - Check if not deployed in a public directory */
        $publicPath = public_path();
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');

        if ($documentRoot !== $publicPath) {
            $message = 'Warning! Insecure deployment: web root not set to public/';
            dd($message);
        }
    }
}
