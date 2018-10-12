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

declare(strict_types = 1);

namespace HoneyComb\Resources\Http\Controllers\Frontend;

use HoneyComb\Core\Http\Controllers\HCBaseController;
use HoneyComb\Resources\Http\Events\Frontend\HCResourceCreated;
use HoneyComb\Resources\Requests\Frontend\HCResourceRequest;
use HoneyComb\Resources\Services\HCResourceService;
use HoneyComb\Starter\Helpers\HCFrontendResponse;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class HCResourceController
 * @package HoneyComb\Resources\Http\Controllers\Frontend
 */
class HCResourceController extends HCBaseController
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
     * @var HCFrontendResponse
     */
    protected $response;

    /**
     * HCResourceController constructor.
     * @param Connection $connection
     * @param HCResourceService $service
     * @param HCFrontendResponse $response
     */
    public function __construct(Connection $connection, HCResourceService $service, HCFrontendResponse $response)
    {
        $this->service = $service;
        $this->connection = $connection;
        $this->response = $response;
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
            $record = $this->service->upload(
                $request->getFile(),
                $request->getLastModified(),
                null,
                null,
                $request->input('preview_sizes', [])
            );

            $data = $request->all();

            if (sizeof($data) > 2) {
                array_forget($data, ['file', 'lastModified']);

                /** @var \HoneyComb\Resources\Models\HCResource $recordM */
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

        event(new HCResourceCreated($record));

        $response = [
            'id' => $record['id'],
            'url' => route('resource.get', $record['id']),
            'storageUrl' => $record['storageUrl'],
        ];

        return $this->response->success('Uploaded', $response);
    }
}
