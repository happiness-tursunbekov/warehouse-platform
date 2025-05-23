<?php

namespace App\Console\Commands;

use App\Services\ConnectWiseService;
use Illuminate\Console\Command;

class PublishProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:publish-product {productId} {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ConnectWiseService $connectWiseService)
    {
        $product = $connectWiseService->getProduct($this->argument('productId'));

        $quantity = $this->argument('quantity');

        $connectWiseService->publishProductOnCin7($product, $quantity, true);

        echo "{$product->id} : {$product->catalogItem->identifier} : {$quantity} successfully published\n";
    }
}
