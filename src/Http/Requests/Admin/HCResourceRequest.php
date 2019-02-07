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

namespace HoneyComb\Resources\Requests\Admin;

use HoneyComb\Resources\Repositories\Admin\HCResourceTagRepository;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Class HCResourceRequest
 * @package HoneyComb\Resources\Requests\Admin
 */
class HCResourceRequest extends FormRequest
{
    /**
     * Get request inputs
     *
     * @return array
     */
    public function getRecordData(): array
    {
        $data = $this->all();
        $data['author_id'] = $this->getAuthorId();

        return $data;
    }

    /**
     * @return null|string
     */
    public function getAuthorId(): ?string
    {
        if ($this->has('author')) {
            return $this->input('author.id');
        }

        return null;
    }

    /**
     * Get ids to delete, force delete or restore
     *
     * @return array
     */
    public function getListIds(): array
    {
        return $this->input('list', []);
    }

    /**
     * Getting translations
     *
     * @return array
     */
    public function getTranslations(): array
    {
        return $this->input('translations', []);
    }

    public function getTags(HCResourceTagRepository $repository)
    {
        $tags = $this->input('tags') ? $this->input('tags') : [];

        $tagIds = [];

        foreach ($tags as $tag) {
            if (is_null($tag['id'])) {
                continue;
            }

            if (array_has($tag, 'className')) {
                $newTag = $repository->makeQuery()->firstOrCreate([
                    'id' => strtolower($tag['id']),
                ], [
                    'name' => $tag['name'],
                ]);

                $tagIds[] = $newTag->id;
            } else {
                if ($tag['id']) {
                    $tagIds[] = $tag['id'];
                }
            }
        }

        return $tagIds;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        switch ($this->method()) {
            case 'POST':
                if ($this->segment(4) == 'restore') {
                    return [
                        'list' => 'required|array',
                    ];
                }

                return [
                    'file' => 'required',
                ];

            case 'PUT':

                return [
                    'translations' => 'required|array|min:1',
                ];

            case 'DELETE':
                return [
                    'list' => 'required|array',
                ];
        }

        return [];
    }
}
