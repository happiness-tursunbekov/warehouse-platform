<?php

namespace App\Console\Commands;

use App\Services\ConnectWiseService;
use Illuminate\Console\Command;

class IncorrectUnitOfMeasure extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:incorrect-unit-of-measure';

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
        $page = 1;

        $count = 0;
        while (true) {
            collect($connectWiseService->getCatalogItems($page, 'unitOfMeasure/id=7 and identifier not contains "-used)"', null, null, 1000))->count();

            $page++;
        }

        echo $count . "\n";
    }
}
