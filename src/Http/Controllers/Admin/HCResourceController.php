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
use HoneyComb\Resources\Events\Admin\Resource\HCResourceCreated;
use HoneyComb\Resources\Events\Admin\Resource\HCResourceForceDeleted;
use HoneyComb\Resources\Events\Admin\Resource\HCResourceRestored;
use HoneyComb\Resources\Events\Admin\Resource\HCResourceSoftDeleted;
use HoneyComb\Resources\Events\Admin\Resource\HCResourceUpdated;
use HoneyComb\Resources\Models\HCResource;
use HoneyComb\Resources\Requests\Admin\HCResourceRequest;
use HoneyComb\Resources\Services\Admin\HCResourceTagService;
use HoneyComb\Resources\Services\HCResourceService;
use HoneyComb\Starter\Helpers\HCResponse;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Class HCResourceController
 * @package HoneyComb\Resources\Http\Controllers\Admin
 */
class HCResourceController extends HCBaseController
{
    use HCAdminListHeaders;

    /**
     * @var HCResourceService
     */
    protected $service;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var HCResponse
     */
    protected $response;
    /**
     * @var \HoneyComb\Resources\Services\Admin\HCResourceTagService
     */
    private $resourceTagService;

    /**
     * HCResourceController constructor.
     * @param Connection $connection
     * @param HCResponse $response
     * @param HCResourceService $service
     * @param \HoneyComb\Resources\Services\Admin\HCResourceTagService $resourceTagService
     */
    public function __construct(
        Connection $connection,
        HCResponse $response,
        HCResourceService $service,
        HCResourceTagService $resourceTagService
    ) {
        $this->connection = $connection;
        $this->response = $response;
        $this->service = $service;
        $this->resourceTagService = $resourceTagService;
    }

    /**
     * Admin panel page view
     *
     * @return View
     */
    public function index(): View
    {
        $config = [
            'title' => trans('HCResource::resource.page_title'),
            'url' => route('admin.api.resource'),
            'form' => route('admin.api.form-manager', ['resource']),
            'headers' => $this->getTableColumns(),
            'actions' => $this->getActions('honey_comb_resources_resource'),
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
            'id' => $this->headerImage(trans('HCResource::resource.preview'), 100, 100, true),
            'uploaded_by' => $this->headerText(trans('HCResource::resource.uploaded_by')),
            'path' => $this->headerText(trans('HCResource::resource.path')),
            'original_name' => $this->headerText(trans('HCResource::resource.original_name')),
            'size' => $this->headerText(trans('HCResource::resource.size')),
            'full_path' => $this->headerCopy(trans('HCResource::resource.full_path'), 'id',
                route('resource.get', '') . '/'),
        ];

        return $columns;
    }

    /**
     * @param string $id
     * @return \HoneyComb\Resources\Models\HCResource|\HoneyComb\Resources\Repositories\Admin\HCResourceRepository|\Illuminate\Database\Eloquent\Model|null
     */
    public function getById(string $id)
    {
        return $this->service->getRepository()->makeQuery()->with([
            'author' => function(HasOne $builder) {
                $builder->select('id', 'name as label');
            },
        ])->find($id);
    }

    /**
     * Creating data list
     * @param HCResourceRequest $request
     * @return JsonResponse
     */
    public function getListPaginate(HCResourceRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getRepository()->getListPaginate($request)
        );
    }

    /**
     * Create data list
     * @param HCResourceRequest $request
     * @return JsonResponse
     */
    public function getOptions(HCResourceRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getRepository()->getOptions($request)
        );
    }

    /**
     * Updating menu group record
     *
     * @param HCResourceRequest $request
     * @param string $id
     * @param bool $returnData
     * @return JsonResponse
     */
    public function update(HCResourceRequest $request, string $id): JsonResponse
    {
        /** @var HCResource $record */
        $record = $this->service->getRepository()->findOneBy(['id' => $id]);
        $record->update($request->getRecordData());
        $record->updateTranslations($request->getTranslations());
        $record->tags()->sync($request->getTags($this->resourceTagService->getRepository()));

        if ($record) {
            $record = $this->service->getRepository()->find($id);

            event(new HCResourceUpdated($record));
        }

        return $this->response->success('Updated', $record);
    }

    /**
     * @param HCResourceRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function deleteSoft(HCResourceRequest $request): JsonResponse
    {
        $this->connection->beginTransaction();

        try {
            $deleted = $this->service->getRepository()->deleteSoft($request->getListIds());

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            report($exception);

            return $this->response->error($exception->getMessage());
        }

        event(new HCResourceSoftDeleted($deleted));

        return $this->response->success('Successfully deleted');
    }

    /**
     * @param HCResourceRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function restore(HCResourceRequest $request): JsonResponse
    {
        $this->connection->beginTransaction();

        try {
            $restored = $this->service->getRepository()->restore($request->getListIds());

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            report($exception);

            return $this->response->error($exception->getMessage());
        }

        event(new HCResourceRestored($restored));

        return $this->response->success('Successfully restored');
    }

    /**
     * @param HCResourceRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function deleteForce(HCResourceRequest $request): JsonResponse
    {
        $this->connection->beginTransaction();

        try {
            $deleted = $this->service->forceDelete($request->getListIds());

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            report($exception);

            return $this->response->error($exception->getMessage());
        }

        event(new HCResourceForceDeleted($deleted));

        return $this->response->success('Successfully deleted');
    }
}
