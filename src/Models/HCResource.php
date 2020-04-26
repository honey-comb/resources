<?php
/**
 * @copyright 2018 interactivesolutions
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
 * Contact InteractiveSolutions:
 * E-mail: hello@interactivesolutions.lt
 * http://www.interactivesolutions.lt
 */

declare(strict_types = 1);

namespace HoneyComb\Resources\Models;

use HoneyComb\Starter\Models\HCUuidSoftModel;
use HoneyComb\Starter\Models\Traits\HCTranslation;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class HCResource
 * @package HoneyComb\Resources\Models
 */
class HCResource extends HCUuidSoftModel
{
    use HCTranslation;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'hc_resource';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'uploaded_by',
        'path',
        'original_name',
        'safe_name',
        'extension',
        'mime_type',
        'size',
        'checksum',
        'original_at',
        'disk',
    ];

    protected $with = [];

    /**
     * Get file path of the resource
     *
     * @return string
     */
    public function file_path(): string
    {
        return storage_path('app' . DIRECTORY_SEPARATOR . $this->path);
    }

    /**
     * Check if resource is image
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return strpos($this->mime_type, 'image') !== false;
    }
}
