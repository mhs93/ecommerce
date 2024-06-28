<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearHomePageProductList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-home-page-product-list {key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear home page product list cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = $this->argument('key');
        Cache::forget($key);
        $this->info("Cache with home product list - $key has cleared successfully.");
    }
}
