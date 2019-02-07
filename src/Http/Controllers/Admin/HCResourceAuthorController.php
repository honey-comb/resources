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
use HoneyComb\Resources\Events\Admin\ResourceAuthor\HCResourceAuthorCreated;
use HoneyComb\Resources\Events\Admin\ResourceAuthor\HCResourceAuthorForceDeleted;
use HoneyComb\Resources\Events\Admin\ResourceAuthor\HCResourceAuthorRestored;
use HoneyComb\Resources\Events\Admin\ResourceAuthor\HCResourceAuthorSoftDeleted;
use HoneyComb\Resources\Events\Admin\ResourceAuthor\HCResourceAuthorUpdated;
use HoneyComb\Resources\Requests\Admin\HCResourceAuthorRequest;
use HoneyComb\Resources\Services\HCResourceAuthorService;
use HoneyComb\Starter\Helpers\HCResponse;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/**
 * Class HCResourceAuthorController
 * @package HoneyComb\Resources\Http\Controllers\Admin
 */
class HCResourceAuthorController extends HCBaseController
{
    use HCAdminListHeaders;

    /**
     * @var HCResourceAuthorService
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
     * HCResourceAuthorController constructor.
     * @param Connection $connection
     * @param HCResponse $response
     * @param HCResourceAuthorService $service
     */
    public function __construct(Connection $connection, HCResponse $response, HCResourceAuthorService $service)
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
            'title' => trans('HCResource::resource_author.page_title'),
            'url' => route('admin.api.resource.author'),
            'form' => route('admin.api.form-manager', ['resource.author']),
            'headers' => $this->getTableColumns(),
            'actions' => $this->getActions('honey_comb_resources_resource_author'),
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
            'name' => $this->headerText(trans('HCResource::resource_author.name')),
        ];

        return $columns;
    }

    /**
     * @param string $id
     * @return \HoneyComb\Resources\Models\HCResourceAuthor|\HoneyComb\Resources\Repositories\Admin\HCResourceAuthorRepository|\Illuminate\Database\Eloquent\Model|null
     */
    public function getById(string $id)
    {
        return $this->service->getRepository()->findOneBy(['id' => $id]);
    }

    /**
     * Creating data list
     * @param HCResourceAuthorRequest $request
     * @return JsonResponse
     */
    public function getListPaginate(HCResourceAuthorRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getRepository()->getListPaginate($request)
        );
    }

    /**
     * Create data list
     * @param HCResourceAuthorRequest $request
     * @return JsonResponse
     */
    public function getOptions(HCResourceAuthorRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getRepository()->getOptions($request)
        );
    }

    /**
     * Creating record
     *
     * @param HCResourceAuthorRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(HCResourceAuthorRequest $request): JsonResponse
    {
        $this->connection->beginTransaction();

        try {
            $resourceAuthor = $this->service->getRepository()->create($request->getRecordData());

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            report($exception);

            return $this->response->error($exception->getMessage());
        }

        event(new HCResourceAuthorCreated($resourceAuthor));

        if ($request->has('hc_new')) {

            $resourceAuthor->label = $resourceAuthor->name;

            return $this->response->success("Created", $resourceAuthor);
        } else {

            return $this->response->success("Created");
        }

    }

    /**
     * Updating menu group record
     *
     * @param HCResourceAuthorRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(HCResourceAuthorRequest $request, string $id): JsonResponse
    {
        $record = $this->service->getRepository()->findOneBy(['id' => $id]);
        $record->update($request->getRecordData());

        if ($record) {
            $record = $this->service->getRepository()->find($id);

            event(new HCResourceAuthorUpdated($record));
        }

        return $this->response->success("Created");
    }

    /**
     * @param HCResourceAuthorRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function deleteSoft(HCResourceAuthorRequest $request): JsonResponse
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

        event(new HCResourceAuthorSoftDeleted($deleted));

        return $this->response->success('Successfully deleted');
    }

    /**
     * @param HCResourceAuthorRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function restore(HCResourceAuthorRequest $request): JsonResponse
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

        event(new HCResourceAuthorRestored($restored));

        return $this->response->success('Successfully restored');
    }

    /**
     * @param HCResourceAuthorRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function deleteForce(HCResourceAuthorRequest $request): JsonResponse
    {
        $this->connection->beginTransaction();

        try {
            $deleted = $this->service->getRepository()->deleteForce($request->getListIds());

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            report($exception);

            return $this->response->error($exception->getMessage());
        }

        event(new HCResourceAuthorForceDeleted($deleted));

        return $this->response->success('Successfully deleted');
    }
}
