<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearCatSubCatCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'category:cache-clear {key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear frontend category,sub category data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = $this->argument('key');
        Cache::forget($key);
        $this->info("Cache of category and sub category has cleared successfully.");
    }
}
