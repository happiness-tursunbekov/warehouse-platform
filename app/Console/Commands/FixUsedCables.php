<?php

namespace App\Console\Commands;

use App\Services\ConnectWiseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FixUsedCables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-used-cables';

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
        $files = Storage::disk('public')->files('photos');

        foreach ($files as $filePath) {
            $file = Storage::get($filePath);

            $image = new \Imagick();
            $image->readImageBlob($file);
            $image->setImageFormat("jpeg");
            $image->setImageCompressionQuality(80);
            $image->writeImage(storage_path("/") . "app/public/" . explode('.', Str::replace('/', '-converted-resized/', $filePath))[0] . ".jpg");

            echo $filePath . "\n";
        }
    }
}
