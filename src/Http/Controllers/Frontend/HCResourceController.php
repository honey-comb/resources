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

declare(strict_types=1);

namespace HoneyComb\Resources\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use HoneyComb\Resources\Services\HCResourceService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class HCResourceController
 * @package HoneyComb\Resources\Http\Controllers\Frontend
 */
class HCResourceController extends Controller
{
    /**
     * @var HCResourceService
     */
    protected $service;

    /**
     * HCResourceController constructor.
     * @param HCResourceService $service
     */
    public function __construct(HCResourceService $service)
    {
        $this->service = $service;
    }

    /**
     * Show resource
     *
     * @param null|string $id
     * @param int|null $width
     * @param int|null $height
     * @param bool|null $fit
     * @return StreamedResponse
     */
    public function show(string $id = null, int $width = 0, int $height = 0, bool $fit = false): StreamedResponse
    {
        return $this->service->show($id, $width, $height, $fit);
    }
}
