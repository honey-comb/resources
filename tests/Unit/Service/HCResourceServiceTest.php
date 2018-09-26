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

namespace Tests\Unit\Service;

use Carbon\Carbon;
use HoneyComb\Resources\Models\HCResource;
use HoneyComb\Resources\Repositories\Admin\HCResourceRepository;
use HoneyComb\Resources\Services\HCResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Class HCResourceServiceTest
 * @package Tests\Unit\Service
 */
class HCResourceServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * @group resource-service
     */
    public function it_must_create_singleton_instance(): void
    {
        $this->assertInstanceOf(HCResourceService::class, $this->getTestClassInstance());

        $this->assertSame($this->getTestClassInstance(), $this->getTestClassInstance());
    }

    /**
     * @test
     * @group resource-service
     */
    public function it_must_get_correct_repository(): void
    {
        $this->assertInstanceOf(HCResourceRepository::class, $this->getTestClassInstance()->getRepository());
    }

    /**
     * @test
     * @group resource-service
     */
    public function it_must_throw_type_error_exception_if_given_file_is_null(): void
    {
        $this->expectException(\TypeError::class);

        $this->getTestClassInstance()->upload(null);
    }

    /**
     * @test
     * @group resource-service
     */
    public function it_must_store_jpg_image_file_to_local_storage(): void
    {
        \Storage::fake('local');

        $fakeFile = UploadedFile::fake()->image('avatar.jpg');
        $fakeFileParams = [
            'id' => '2f2ba74c-3d4a-4eec-9e6f-90a8a9af2785',
            'disk' => 'local',
            'original_name' => 'avatar.jpg',
            'extension' => '.jpg',
            'safe_name' => '2f2ba74c-3d4a-4eec-9e6f-90a8a9af2785.jpg',
            'path' => 'uploads/' . Carbon::now()->toDateString() . '/2f2ba74c-3d4a-4eec-9e6f-90a8a9af2785.jpg',
            'size' => $fakeFile->getSize(),
            'mime_type' => 'image/jpeg',
            'uploaded_by' => null,
            'original_at' => null,
        ];

        $fakeModel = new HCResource($fakeFileParams);

        $m = Mockery::mock(HCResourceRepository::class);
        $m->shouldReceive('create')
            ->once()
            ->with($fakeFileParams)
            ->andReturn($fakeModel);
        $this->app->instance(HCResourceRepository::class, $m);

        $result = $this->getTestClassInstance()->upload(
            $fakeFile,
            null,
            'local',
            '2f2ba74c-3d4a-4eec-9e6f-90a8a9af2785'
        );

        // Assert the file was stored...
        Storage::disk('local')->assertExists($fakeModel->path);

        $this->assertEquals($fakeModel->toArray() + ['storageUrl' => null], $result);
    }

    /**
     * @return HCResourceService
     */
    private function getTestClassInstance(): HCResourceService
    {
        return $this->app->make(HCResourceService::class);
    }
}
