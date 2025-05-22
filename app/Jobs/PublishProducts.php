<?php

namespace App\Jobs;

use App\Services\ConnectWiseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class PublishProducts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    private Collection $items;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection $items)
    {
        $this->items = $items;
    }

    /**
     * Execute the job.
     */
    public function handle(ConnectWiseService $connectWiseService): void
    {
        $connectWiseService->setCin7HandleApiLimitation(true);

        $this->items->map(fn($item) => $connectWiseService->publishProductOnCin7($item['product'], $item['quantity'], true));
    }
}
