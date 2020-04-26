<?php
/**
 * @copyright 2018 interactivesolutions
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the 'Software'), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
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

declare(strict_types=1);

namespace HoneyComb\Resources\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\DTO\ResourceDTO;
use HoneyComb\Resources\Models\HCResource;
use HoneyComb\Resources\Requests\HCResourceRequest;
use HoneyComb\Resources\Services\HCResourceService;
use HoneyComb\Starter\Helpers\HCResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;

/**
 * Class HCResourceController
 * @package HoneyComb\Resources\Http\Controllers
 */
class HCResourceController extends Controller
{
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
    private $response;

    /**
     * HCResourceController constructor.
     * @param Connection $connection
     * @param HCResponse $response
     * @param HCResourceService $service
     */
    public function __construct(
        Connection $connection,
        HCResponse $response,
        HCResourceService $service
    )
    {
        $this->connection = $connection;
        $this->response = $response;
        $this->service = $service;
    }

    /**
     * @param string $id
     * @return array
     */
    public function getById(string $id): array
    {
        /** @var HCResource $record */
        $record = $this->service->getById($id);

        return (new ResourceDTO())->setModel($record)->jsonData();
    }

    /**
     * @param HCResourceRequest $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function getListPaginate(HCResourceRequest $request): JsonResponse
    {
        $data = $this->service->getListPaginate($request);

        return response()->json($data);
    }

    /**
     * Create data list
     * @param HCResourceRequest $request
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function getOptions(HCResourceRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->getRepository()->getOptions($request)
        );
    }

    /**
     * Store record
     *
     * @param HCResourceRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function store(HCResourceRequest $request): JsonResponse
    {
        $this->connection->beginTransaction();

        try {
            /** @var HCResource $record */
            $record = $this->service->upload(
                $request->getFile(),
                $request->getLastModified(),
                null,
                null,
                $request->input('previewSizes', [])
            );

            $data = $request->all();

            if (sizeof($data) > 2) {
                array_forget($data, ['file', 'lastModified']);

                /** @var HCResource $recordM */
                $recordM = $this->service->getRepository()->find($record['id']);
                $recordM->update($data);

                $translation = [
                    'language_code' => app()->getLocale(),
                    'label' => '',
                ];

                foreach ($data as $key => $value) {
                    if (strpos($key, 'translation_') !== false) {
                        $key = explode('_', $key)[1];

                        $translation[$key] = $value;
                    } else {
                        if ($key === 'tags') {
                            $recordM->tags()->sync(explode(',', $value));
                        }
                    }
                }

                $recordM->translation()->create($translation);
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            report($exception);

            return $this->response->error($exception->getMessage());
        }

        $response = [
            'id' => $record['id'],
            'url' => route('resource.get', $record['id']),
            'storageUrl' => $record['storageUrl'],
        ];

        return $this->response->success('Uploaded', $response);
    }

    /**
     * @param HCResourceRequest $request
     * @param string $id
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function update(HCResourceRequest $request, string $id): JsonResponse
    {
        /** @var HCResource $record */
        $record = $this->service->getRepository()->findOneBy(['id' => $id]);
        $record->update($request->all());

        if ($record) {
            $record = $this->service->getRepository()->find($id);
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

        return $this->response->success('Successfully deleted');
    }
}
