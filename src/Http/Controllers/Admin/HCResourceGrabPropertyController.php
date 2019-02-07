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

use HoneyComb\Resources\Services\Admin\HCResourceGrabPropertyService;
use HoneyComb\Resources\Http\Requests\Admin\HCResourceGrabPropertyRequest;
use HoneyComb\Resources\Models\HCResourceGrabProperty;

use HoneyComb\Core\Http\Controllers\HCBaseController;
use HoneyComb\Core\Http\Controllers\Traits\HCAdminListHeaders;
use HoneyComb\Starter\Helpers\HCResponse;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HCResourceGrabPropertyController extends HCBaseController
{
    use HCAdminListHeaders;

    /**
     * @var HCResourceGrabPropertyService
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
     * HCResourceGrabPropertyController constructor.
     * @param Connection $connection
     * @param HCResponse $response
     * @param HCResourceGrabPropertyService $service
     */
    public function __construct(Connection $connection, HCResponse $response, HCResourceGrabPropertyService $service)
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
            'title' => trans('HCResource::resource_grab_property.page_title'),
            'url' => route('admin.api.resource.grab.property'),
            'form' => route('admin.api.form-manager', ['resource.grab.property']),
            'headers' => $this->getTableColumns(),
            'actions' => ['search']
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
            'id' => $this->headerText(trans('HCResource::resource_grab_property.id')),'thumbnail_id' => $this->headerText(trans('HCResource::resource_grab_property.thumbnail_id')),'resource_id' => $this->headerText(trans('HCResource::resource_grab_property.resource_id')),'source_id' => $this->headerText(trans('HCResource::resource_grab_property.source_id')),'source_type' => $this->headerText(trans('HCResource::resource_grab_property.source_type')),'x' => $this->headerText(trans('HCResource::resource_grab_property.x')),'y' => $this->headerText(trans('HCResource::resource_grab_property.y')),'zoom' => $this->headerText(trans('HCResource::resource_grab_property.zoom')),
        ];

        return $columns;
    }

    /**
    * @param string $id
    * @return HCResourceGrabProperty|null
    */
   public function getById (string $id): ? HCResourceGrabProperty
   {
       return $this->service->getRepository()->findOneBy(['id' => $id]);
   }

   /**
    * Creating data list
    * @param HCResourceGrabPropertyRequest $request
    * @return JsonResponse
    */
   public function getListPaginate(HCResourceGrabPropertyRequest $request): JsonResponse
   {
       return response()->json(
           $this->service->getRepository()->getListPaginate($request)
       );
   }

   /**
   * Create data list
   * @param HCResourceGrabPropertyRequest $request
   * @return JsonResponse
   */
      public function getOptions(HCResourceGrabPropertyRequest $request): JsonResponse
      {
          return response()->json(
              $this->service->getRepository()->getOptions($request)
          );
      }

   /**
 * Create record
 *
 * @param HCResourceGrabPropertyRequest $request
 * @return JsonResponse
 * @throws \Throwable
 */
public function store (HCResourceGrabPropertyRequest $request): JsonResponse
{
    $this->connection->beginTransaction();

    try {

        $this->service->generateThumbs($request->input('images'));


        $this->connection->commit();
    } catch (\Throwable $e) {
        $this->connection->rollBack();

        return $this->response->error($e->getMessage());
    }

    return $this->response->success("Created");
}


   /**
 * Update record
 *
 * @param HCResourceGrabPropertyRequest $request
 * @param string $id
 * @return JsonResponse
 */
public function update(HCResourceGrabPropertyRequest $request, string $id): JsonResponse
{
    $model = $this->service->getRepository()->findOneBy(['id' => $id]);
    $model->update($request->getRecordData());

    return $this->response->success("Created");
}


   /**
 * Soft delete record
 *
 * @param HCResourceGrabPropertyRequest $request
 * @return JsonResponse
 * @throws \Throwable
 */
public function deleteSoft(HCResourceGrabPropertyRequest $request): JsonResponse
{
    $this->connection->beginTransaction();

    try {
        $this->service->getRepository()->deleteSoft($request->getListIds());

        $this->connection->commit();
    } catch (\Throwable $exception) {
        $this->connection->rollBack();

        return $this->response->error($exception->getMessage());
    }

    return $this->response->success('Successfully deleted');
}


   

   
}