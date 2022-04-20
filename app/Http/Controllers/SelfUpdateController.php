<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Exceptions\FilePermissionsFailure;
use App\Utils\Ninja;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SelfUpdateController extends BaseController
{
    use DispatchesJobs;

    private array $purge_file_list = [
        'bootstrap/cache/compiled.php',
        'bootstrap/cache/config.php',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
        'bootstrap/cache/routes-v7.php',
        'bootstrap/cache/livewire-components.php',
    ];

    public function __construct()
    {
    }

    /**
     * @OA\Post(
     *      path="/api/v1/self-update",
     *      operationId="selfUpdate",
     *      tags={"update"},
     *      summary="Performs a system update",
     *      description="Performs a system update",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Password"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Success/failure response"
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    // public function old_update(\Codedge\Updater\UpdaterManager $updater)
    // {
    //     set_time_limit(0);
    //     define('STDIN', fopen('php://stdin', 'r'));

    //     if (Ninja::isHosted()) {
    //         return response()->json(['message' => ctrans('texts.self_update_not_available')], 403);
    //     }

    //     $this->testWritable();

    //     // Get the new version available
    //     $versionAvailable = $updater->source()->getVersionAvailable();

    //     // Create a release
    //     $release = $updater->source()->fetch($versionAvailable);

    //     $updater->source()->update($release);

            
    //     $cacheCompiled = base_path('bootstrap/cache/compiled.php');
    //     if (file_exists($cacheCompiled)) { unlink ($cacheCompiled); }
    //     $cacheServices = base_path('bootstrap/cache/services.php');
    //     if (file_exists($cacheServices)) { unlink ($cacheServices); }

    //     Artisan::call('clear-compiled');
    //     Artisan::call('route:clear');
    //     Artisan::call('view:clear');
    //     Artisan::call('optimize');

    //     return response()->json(['message' => 'Update completed'], 200);

    // }

    public function update()
    {

        set_time_limit(0);
        define('STDIN', fopen('php://stdin', 'r'));

        if (Ninja::isHosted()) {
            return response()->json(['message' => ctrans('texts.self_update_not_available')], 403);
        }

        $this->testWritable();

        copy($this->getDownloadUrl(), storage_path('app/invoiceninja.zip'));

        $file = Storage::disk('local')->path('invoiceninja.zip');

        $zipFile = new \PhpZip\ZipFile();

        $zipFile->openFile($file);

        $zipFile->extractTo(base_path());

        $zipFile->close();

        unlink($file);

        foreach($this->purge_file_list as $purge_file_path)
        {
            $purge_file = base_path($purge_file_path);
            if (file_exists($purge_file)) { unlink ($purge_file); }

        }

        Artisan::call('clear-compiled');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize');

        return response()->json(['message' => 'Update completed'], 200);


    }

    private function testWritable()
    {
        $directoryIterator = new \RecursiveDirectoryIterator(base_path(), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {

            if(strpos($file->getPathname(), '.git') !== false)
                continue;

            //nlog($file->getPathname());

            if ($file->isFile() && ! $file->isWritable()) {
                // throw new FilePermissionsFailure($file);
                nlog("Cannot update system because {$file->getFileName()} is not writable");
                throw new FilePermissionsFailure("Cannot update system because {$file->getFileName()} is not writable");
                return false;
            }
        }

        return true;
    }

    public function checkVersion()
    {
        return trim(file_get_contents(config('ninja.version_url')));
    }

    private function getDownloadUrl()
    {
        $version = $this->checkVersion();

        return "https://github.com/invoiceninja/invoiceninja/releases/download/v{$version}/invoiceninja.zip";
    }
}
