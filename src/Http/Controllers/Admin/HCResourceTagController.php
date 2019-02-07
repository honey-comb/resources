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

use HoneyComb\Resources\Services\Admin\HCResourceTagService;
use HoneyComb\Resources\Http\Requests\Admin\HCResourceTagRequest;
use HoneyComb\Resources\Models\HCResourceTag;

use HoneyComb\Core\Http\Controllers\HCBaseController;
use HoneyComb\Core\Http\Controllers\Traits\HCAdminListHeaders;
use HoneyComb\Starter\Helpers\HCResponse;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class HCResourceTagController extends HCBaseController
{
    use HCAdminListHeaders;

    /**
     * @var HCResourceTagService
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
     * HCResourcesTagController constructor.
     * @param Connection $connection
     * @param HCResponse $response
     * @param HCResourceTagService $service
     */
    public function __construct(Connection $connection, HCResponse $response, HCResourceTagService $service)
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
            'title' => trans('HCResource::resources_tags.page_title'),
            'url' => route('admin.api.resource.tag'),
            'form' => route('admin.api.form-manager', ['resource.tag']),
            'headers' => $this->getTableColumns(),
            'actions' => $this->getActions('honey_comb_resources_resources_tags'),
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
            'id' => $this->headerText(trans('HCResource::resources_tags.id')),'label' => $this->headerText(trans('HCResource::resources_tags.label')),
        ];

        return $columns;
    }

    /**
    * @param string $id
    * @return HCResourceTag|null
    */
   public function getById (string $id): ? HCResourceTag
   {
       return $this->service->getRepository()->findOneBy(['id' => $id]);
   }

   /**
    * Creating data list
    * @param HCResourceTagRequest $request
    * @return JsonResponse
    */
   public function getListPaginate(HCResourceTagRequest $request): JsonResponse
   {
       return response()->json(
           $this->service->getRepository()->getListPaginate($request)
       );
   }

   /**
   * Create data list
   * @param HCResourceTagRequest $request
   * @return JsonResponse
   */
      public function getOptions(HCResourceTagRequest $request): JsonResponse
      {
          return response()->json(
              $this->service->getRepository()->getOptions($request)
          );
      }

   /**
 * Create record
 *
 * @param HCResourceTagRequest $request
 * @return JsonResponse
 * @throws \Throwable
 */
public function store (HCResourceTagRequest $request): JsonResponse
{
    $this->connection->beginTransaction();

    try {
        $model = $this->service->getRepository()->create($request->getRecordData());

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
 * @param HCResourceTagRequest $request
 * @param string $id
 * @return JsonResponse
 */
public function update(HCResourceTagRequest $request, string $id): JsonResponse
{
    $model = $this->service->getRepository()->findOneBy(['id' => $id]);
    $model->update($request->getRecordData());

    return $this->response->success("Created");
}


   /**
 * Soft delete record
 *
 * @param HCResourceTagRequest $request
 * @return JsonResponse
 * @throws \Throwable
 */
public function deleteSoft(HCResourceTagRequest $request): JsonResponse
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


   /**
 * Restore record
 *
 * @param HCResourceTagRequest $request
 * @return JsonResponse
 * @throws \Throwable
 */
public function restore(HCResourceTagRequest $request): JsonResponse
{
    $this->connection->beginTransaction();

    try {
        $this->service->getRepository()->restore($request->getListIds());

        $this->connection->commit();
    } catch (\Throwable $exception) {
        $this->connection->rollBack();

        return $this->response->error($exception->getMessage());
    }

    return $this->response->success('Successfully restored');
}


   
}