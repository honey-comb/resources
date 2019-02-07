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

namespace HoneyComb\Resources\Repositories\Admin;

use HoneyComb\Resources\Models\HCResourceAuthor;
use HoneyComb\Resources\Requests\Admin\HCResourceAuthorRequest;
use HoneyComb\Starter\Repositories\HCBaseRepository;
use HoneyComb\Starter\Repositories\Traits\HCQueryBuilderTrait;

/**
 * Class HCResourceAuthorRepository
 * @package HoneyComb\Resources\Repositories\Admin
 */
class HCResourceAuthorRepository extends HCBaseRepository
{
    use HCQueryBuilderTrait;

    /**
     * @return string
     */
    public function model(): string
    {
        return HCResourceAuthor::class;
    }

    /**
     * Soft deleting records
     * @param $ids
     * @throws \Exception
     */
    public function deleteSoft(array $ids): array
    {
        $deleted = [];

        $records = $this->makeQuery()->whereIn('id', $ids)->get();

        /** @var HCResourceAuthor $record */
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

        foreach ($records as $record) {
            /** @var HCResourceAuthor $record */
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
            /** @var HCResourceAuthor $record */
            if ($record->forceDelete()) {
                $deleted[] = $record;
            }
        }

        return $deleted;
    }

    /**
     * @param \HoneyComb\Resources\Requests\Admin\HCResourceAuthorRequest $request
     * @return \Illuminate\Support\Collection|static
     */
    public function getOptions(HCResourceAuthorRequest $request)
    {
        return $this->createBuilderQuery($request)->get()->map(function ($record) {
            return [
                'id' => $record->id,
                'label' => $record->name,
            ];
        });
    }
}
