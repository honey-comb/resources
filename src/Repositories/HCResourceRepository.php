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

declare(strict_types=1);

namespace HoneyComb\Resources\Repositories;

use HoneyComb\Resources\Models\HCResource;
use HoneyComb\Resources\Requests\HCResourceRequest;
use HoneyComb\Starter\Repositories\HCBaseRepository;
use HoneyComb\Starter\Repositories\Traits\HCQueryBuilderTrait;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Class HCResourceRepository
 * @package HoneyComb\Resources\Repositories\Admin
 */
class HCResourceRepository extends HCBaseRepository
{
    use HCQueryBuilderTrait;

    /**
     * @return string
     */
    public function model(): string
    {
        return HCResource::class;
    }

    /**
     * @param HCResource $resource
     * @throws BindingResolutionException
     */
    public function updateChecksum(HCResource $resource): void
    {
        if ($resource->disk == 'local') {
            $filePath = config('filesystems.disks.local.root') . DIRECTORY_SEPARATOR . $resource->path;
        } elseif ($resource->disk == 'public') {
            $filePath = config('filesystems.disks.public.root') . DIRECTORY_SEPARATOR . $resource->path;
        } else {
            $filePath = Storage::disk($resource->disk)->url($resource->path);
        }

        $resource->update(['checksum' => hash_file('sha256', $filePath)]);
    }

    /**
     * @param array $ids
     * @return array
     * @throws \Exception
     */
    public function deleteSoft(array $ids): array
    {
        $deleted = [];

        $records = $this->makeQuery()->whereIn('id', $ids)->get();

        /** @var HCResource $record */
        foreach ($records as $record) {
            if ($record->delete()) {
                $deleted[] = $record;
            }
        }

        return $deleted;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function restore(array $ids): array
    {
        $restored = [];

        $records = $this->makeQuery()->withTrashed()->whereIn('id', $ids)->get();

        /** @var HCResource $record */
        foreach ($records as $record) {
            if ($record->restore()) {
                $restored[] = $record;
            }
        }

        return $restored;
    }

    /**
     * @param array $ids
     * @return array
     */
    public function deleteForce(array $ids): array
    {
        $deleted = [];

        $records = $this->makeQuery()->withTrashed()->whereIn('id', $ids)->get();

        foreach ($records as $record) {
            /** @var HCResource $record */
            if ($record->translations()->forceDelete() && $record->forceDelete()) {
                $deleted[] = $record;
            }
        }

        return $deleted;
    }

    /**
     * @param HCResourceRequest $request
     * @return Collection
     * @throws BindingResolutionException
     */
    public function getOptions(HCResourceRequest $request): Collection
    {
        $limit = $request->input('limit', 100);

        return $this->createBuilderQuery($request)->limit($limit)->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'path' => $record->path,
                ];
            });
    }
}
