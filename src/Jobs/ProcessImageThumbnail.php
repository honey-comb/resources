<?php
/**
 * @copyright 2018 innovationbase
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

namespace HoneyComb\Resources\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Intervention\Image\Facades\Image;

/**
 * Class ProcessImageThumbnail
 * @package HoneyComb\Resources\Jobs
 */
class ProcessImageThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var array
     */
    private $image;

    /**
     * @var array
     */
    private $resource;

    /**
     * @var array
     */
    private $thumb;

    /**
     * Create a new job instance.
     *
     * @param array $image
     * @param array $resource
     * @param array $thumb
     */
    public function __construct(array $image, array $resource, array $thumb)
    {
        $this->image = $image;
        $this->resource = $resource;
        $this->thumb = $thumb;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $source = config('filesystems.disks.local.root') . DIRECTORY_SEPARATOR . $this->resource['path'];
        $destination = config('filesystems.disks.local.root') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
            . $this->image['source_type'] . DIRECTORY_SEPARATOR
            . $this->image['source_id'] . DIRECTORY_SEPARATOR
            . $this->image['thumbnail_id'] . DIRECTORY_SEPARATOR;

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $destination .= $this->resource['id'] . $this->resource['extension'];

         /** @var \Intervention\Image\Image $image */
        $image = Image::make($source);
        $image->crop((integer)$this->image['width'], (integer)$this->image['height'], (integer)$this->image['x'], (integer)$this->image['y']);
        $image->resize($this->thumb['width'], $this->thumb['height']);
        $image->save($destination);
        $image->destroy();
    }
}
