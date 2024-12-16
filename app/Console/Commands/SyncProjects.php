<?php

namespace App\Console\Commands;

use App\Services\BigCommerceService;
use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-projects';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ConnectWiseService $connectWiseService, BigCommerceService $bigCommerceService)
    {
        $projects = collect($connectWiseService->getProjects(null, 'closedFlag=false'));
//            ->map(function ($project) use ($bigCommerceService, $connectWiseService) {
//
//            $bigCommerceService->createCustomerGroup([
//                "name" => "#{$project->id} - {$project->name} - {$project->company->name}",
//                "is_default" => false,
//                "category_access" => [
//                    "type" => "all"
//                ],
//                "is_group_for_guests" => false
//            ]);
//            return $project;
//        });
//        while (true) {
//            $groups = collect($bigCommerceService->getCustomerGroups()->data)->map(function ($group) use ($projects, $connectWiseService) {
//                return $connectWiseService->setProjectBigcommerceGroupId($projects->where('id', Str::numbers(explode(' - ', $group->name)[0]))->first(), $group->id);
//            });
//
//            if ($groups->count() < 250)
//                break;
//        }

        print_r($bigCommerceService->getCustomerGroups());
    }
}
