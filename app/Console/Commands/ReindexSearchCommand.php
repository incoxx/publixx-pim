<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\UpdateSearchIndex;
use App\Models\Product;
use Illuminate\Console\Command;

class ReindexSearchCommand extends Command
{
    protected $signature = 'search:reindex {--sync : Run synchronously instead of dispatching to queue}';

    protected $description = 'Reindex all products in the search index';

    public function handle(): int
    {
        $query = Product::where('status', 'active')
            ->where('product_type_ref', 'product');

        $total = $query->count();
        $this->info("Reindexing {$total} products...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->select('id')->chunkById(500, function ($products) use ($bar) {
            foreach ($products as $product) {
                if ($this->option('sync')) {
                    dispatch_sync(new UpdateSearchIndex($product->id));
                } else {
                    UpdateSearchIndex::dispatch($product->id);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
