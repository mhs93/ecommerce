<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearProductDetailsForntendCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-product-details-forntend-cache {key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear product details cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = "cached_details".$this->argument('key');
        Cache::forget($key);
        $this->info("Cache with product details - $key has cleared successfully.");
    }
}
