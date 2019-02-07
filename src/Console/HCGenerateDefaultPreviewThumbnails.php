<?php
/**
 * @copyright 2019 innovationbase
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * Contact InnovationBase:
 * E-mail: hello@innovationbase.eu
 * https://innovationbase.eu
 */

namespace HoneyComb\Resources\Console;

use HoneyComb\Resources\Services\HCResourceService;
use Illuminate\Console\Command;

/**
 * Class HCGenerateDefaultPreviewThumbnails
 * @package HoneyComb\Resources\Console
 */
class HCGenerateDefaultPreviewThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hc-resources:preview-thumbs {start=0} {end=1000} {disk=local}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates preview thumbnails in storage public disk';
    /**
     * @var HCResourceService
     */
    private $resourceService;

    /**
     * Create a new command instance.
     *
     * @param HCResourceService $resourceService
     */
    public function __construct(HCResourceService $resourceService)
    {
        parent::__construct();
        $this->resourceService = $resourceService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $start = $this->argument('start');
        $end = $this->argument('end');
        $disk = $this->argument('disk');

        $resources = $this->resourceService->getRepository()->makeQuery()
            ->select('id', 'path', 'mime_type')
            ->setEagerLoads([])
            ->skip($start)
            ->take($end)
            ->get();

        foreach ($resources as $resource) {
            $this->resourceService->createPreviewThumb(
                $resource->id,
                $resource->path,
                $resource->mime_type,
                $disk,
                [],
                true
            );
        }
    }
}
