<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearFrontendSearchCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-frontend-search {key}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear frontend search product cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = $this->argument('key');
        Cache::forget($key);
        $this->info("Cache with product and page - $key has cleared successfully.");
    }
}
