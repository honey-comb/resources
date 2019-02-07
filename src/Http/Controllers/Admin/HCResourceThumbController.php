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

declare(strict_types = 1);

namespace HoneyComb\Resources\Http\Controllers\Admin;

use HoneyComb\Core\Http\Controllers\HCBaseController;
use HoneyComb\Core\Http\Controllers\Traits\HCAdminListHeaders;
use HoneyComb\Resources\Http\Requests\HCResourceThumbRequest;
use HoneyComb\Resources\Services\HCResourceThumbService;
use HoneyComb\Starter\Helpers\HCResponse;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HCResourceThumbController extends HCBaseController
{
    use HCAdminListHeaders;

    /**
     * @var HCResourceThumbService
     */
    protected $service;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var HCResponse
     */
    private $response;

    /**
     * HCResourceThumbController constructor.
     * @param Connection $connection
     * @param HCResponse $response
     * @param HCResourceThumbService $service
     */
    public function __construct(Connection $connection, HCResponse $response, HCResourceThumbService $service)
    {
        $this->connection = $connection;
        $this->response = $response;
        $this->service = $service;
    }

    /**
     * Admin panel page view
     *
     * @return View
     */
    public function index(): View
    {
        $config = [
            'title' => trans('HCResource::resource_thumb.page_title'),
            'url' => route('admin.api.resource.thumb'),
            'form' => route('admin.api.form-manager', ['resource.thumb']),
            'headers' => $this->getTableColumns(),
            'actions' => $this->getActions('honey_comb_resources_resource_thumb'),
        ];

        return view('HCCore::admin.service.index', ['config' => $config]);
    }

    /**
     * Get admin page table columns settings
     *
     * @return array
     */
    public function getTableColumns(): array
    {
        $columns = [
            'id' => $this->headerText(trans('HCResource::resource_thumb.id')),
            'description' => $this->headerText(trans('HCResource::resource_thumb.description')),
            'width' => $this->headerText(trans('HCResource::resource_thumb.width')),
            'height' => $this->headerText(trans('HCResource::resource_thumb.height')),
            'fit' => $this->headerText(trans('HCResource::resource_thumb.fit')),
            'grab_enabled' => $this->headerText(trans('HCResource::resource_thumb.grab_enabled')),
        ];

        return $columns;
    }

    /**
     * @param string $id
     * @return \HoneyComb\Resources\Models\HCResourceThumb|\HoneyComb\Resources\Repositories\HCResourceThumbRepository|\Illuminate\Database\Eloquent\Model|null
     */
    public function getById(string $id)
    {
        return $this->service->getRepository()->findOneBy(['id' => $id]);
    }

    /**
     * Creating data list
     * @param HCResourceThumbRequest $request
     * @return JsonResponse
     */
    public function getListPaginate(HCResourceThumbRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getRepository()->getListPaginate($request)
        );
    }

    /**
     * Create data list
     * @param HCResourceThumbRequest $request
     * @return JsonResponse
     */
    public function getOptions(HCResourceThumbRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getRepository()->getOptions($request)
        );
    }


    /**
     * Update record
     *
     * @param HCResourceThumbRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(HCResourceThumbRequest $request, string $id): JsonResponse
    {
        $model = $this->service->getRepository()->findOneBy(['id' => $id]);
        $model->update($request->getRecordData());

        return $this->response->success("Created");
    }


}
