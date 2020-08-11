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

declare(strict_types=1);

namespace HoneyComb\Resources\Services;

use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\Filters\Video\RotateFilter;
use FFMpeg\Format\Video\X264;
use FFMpeg\Media\Audio;
use FFMpeg\Media\Video;
use HoneyComb\Resources\Http\DTO\ResourceDTO;
use HoneyComb\Resources\Jobs\ProcessPreviewImage;
use HoneyComb\Resources\Models\HCResource;
use HoneyComb\Resources\Repositories\HCResourceRepository;
use HoneyComb\Resources\Requests\HCResourceRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Constraint;
use Intervention\Image\Facades\Image;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Class HCResourceService
 * @package HoneyComb\Resources\Services
 */
class HCResourceService
{
    /**
     * File upload location
     *
     * @var string
     */
    protected $uploadPath;

    /**
     * @var HCResourceRepository
     */
    protected $repository;

    /**
     * Should the checksum be validated and duplicates found
     *
     * @var bool
     */
    protected $allowDuplicates;

    /**
     * Amount of attempts for file headers loading, to be able to repeat the call
     *
     * @var int
     */
    protected $headerCallAttempts = 0;

    /**
     * HCResourceService constructor.
     *
     * @param HCResourceRepository $repository
     */
    public function __construct(HCResourceRepository $repository)
    {
        $this->repository = $repository;

        $this->uploadPath = sprintf('uploads/%s/', date('Y-m-d'));
    }

    /**
     * @param bool $allow
     * @return HCResourceService
     */
    public function setAllowDuplicates(bool $allow): HCResourceService
    {
        $this->allowDuplicates = $allow;

        return $this;
    }

    /**
     * @param null|string $id
     * @param int $width
     * @param int $height
     * @param bool $fit
     * @return StreamedResponse
     */
    public function show(string $id, int $width, int $height, bool $fit): StreamedResponse
    {
        if (is_null($id)) {
            logger()->info('resourceId is null');
            exit;
        }

        $resource = $this->getRepository()->find($id);

        if (!$resource) {
            logger()->info('File record not found', ['id' => $id]);
            exit;
        }

        if (!Storage::disk($resource->disk)->exists($resource->path)) {
            logger()->info('File not found in storage', ['id' => $id, 'path' => $resource->path]);

            return $this->showNotFound($resource->mime_type);
            exit;
        }

        $cachePath = $this->generateCachePath(
            $resource->id,
            $resource->disk,
            $resource->extension,
            $width,
            $height,
            $fit
        );

        if (Storage::disk($resource->disk)->exists($cachePath)) {
            //set cache details
            $resource->size = Storage::disk($resource->disk)->size($cachePath);
            $resource->path = $cachePath;
        } else {
            switch ($resource->mime_type) {
                case 'text/plain':
                    if ($resource->extension == '.svg') {
                        $resource->mime_type = 'image/svg+xml';
                    }
                    break;

                case 'image/svg':
                    if ($resource->mime_type = 'image/svg') {
                        $resource->mime_type = 'image/svg+xml';
                    }
                    break;

                case 'image/jpg':
                case 'image/png':
                case 'image/jpeg':
                    if ($width != 0 && $height != 0) {
                        $this->createImage(
                            $resource->disk,
                            $resource->path,
                            $cachePath,
                            $width,
                            $height,
                            $fit
                        );

                        $resource->size = Storage::disk($resource->disk)->size($cachePath);
                        $resource->path = $cachePath;
                    }
                    break;

                case 'video/mp4':

                    if (Storage::disk($resource->disk)->exists($cachePath)) {
                        $resource->size = Storage::disk($resource->disk)->size($cachePath);
                        $resource->path = $cachePath;
                        $resource->mime_type = 'image/jpg';
                    } else {
                        if ($width != 0 && $height != 0) {

                            $previewPath = str_replace('-', '/', $resource->id);
                            $fullPreviewPath = 'video-previews/' . $previewPath;

                            $cachePath = explode('.', $cachePath)[0] . '.jpg';

                            $this->generateVideoPreview($resource, $fullPreviewPath);

                            $this->createImage($resource->disk,
                                $fullPreviewPath . '/preview_frame.jpg',
                                $cachePath,
                                $width,
                                $height,
                                $fit);

                            $resource->size = Storage::disk($resource->disk)->size($cachePath);
                            $resource->path = $cachePath;
                            $resource->mime_type = 'image/jpg';
                        }
                    }
                    break;
            }
        }

        $headers = [
            'Content-Type' => $resource->mime_type,
            'Content-Length' => $resource->size,
        ];

        return Storage::disk($resource->disk)
            ->response($resource->path, $this->getOriginalFileName($resource), $headers);
    }

    /**
     * @return HCResourceRepository
     */
    public function getRepository(): HCResourceRepository
    {
        return $this->repository;
    }

    /**
     * @param $mimeType
     * @return StreamedResponse
     */
    private function showNotFound($mimeType): StreamedResponse
    {
        $fileNotFound = config('resources.file_not_found');

        switch ($mimeType) {
            case 'video/mp4':
                $notFound = $fileNotFound['video'];
                break;
            case 'text/plain':
            case 'application/pdf':
            case 'application/octet-stream':
                $notFound = $fileNotFound['file'];
                break;
            default:
                $notFound = $fileNotFound['image'];
        }

        $headers = [
            'Content-Type' => 'image/svg+xml',
        ];

        return new StreamedResponse(function () use ($notFound) {
            echo $notFound;
        }, 200, $headers);
    }

    /**
     * Generating resource cache location and name
     *
     * @param string $resourceId
     * @param string $disk
     * @param string $extension
     * @param int|null $width
     * @param int|null $height
     * @param null $fit
     * @return string
     */
    protected function generateCachePath(
        string $resourceId,
        string $disk,
        string $extension,
        int $width = 0,
        int $height = 0,
        $fit = null
    ): string
    {
        if ($this->isLocalOrPublic($disk)) {
            $folder = config('filesystems.disks.' . $disk . '.root') . '/cache/' . str_replace('-', '/', $resourceId);

            if (!is_dir($folder)) {
                mkdir($folder, 0755, true);
            }
        }

        $path = 'cache/' . str_replace('-', '/', $resourceId) . '/';

        $path .= $width . '_' . $height;

        if ($fit) {
            $path .= '_fit';
        }

        return $path . $extension;
    }

    /**
     * @param string $disk
     * @return bool
     */
    protected function isLocalOrPublic(string $disk): bool
    {
        return $disk == 'local' || $disk == 'public';
    }

    /**
     * Creating image based on provided data
     *
     * @param string $disk
     * @param $source
     * @param $destination
     * @param int $width
     * @param int $height
     * @param bool $fit
     * @return bool
     */
    protected function createImage(string $disk, $source, $destination, $width = 0, $height = 0, $fit = false): bool
    {
        $source2 = $source;

        if ($width == 0) {
            $width = null;
        }

        if ($height == 0) {
            $height = null;
        }

        if ($this->isLocalOrPublic($disk)) {
            $source = config('filesystems.disks.' . $disk . '.root') . DIRECTORY_SEPARATOR . $source;
            $destination = config('filesystems.disks.' . $disk . '.root') . DIRECTORY_SEPARATOR . $destination;
        } else {
            $source = Storage::disk($disk)->url($source);
        }

        /** @var \Intervention\Image\Image $image */
        $image = Image::make($source);

        if ($fit) {
            $image->fit($width, $height, function (Constraint $constraint) {
                $constraint->upsize();
            });
        } else {
            $image->resize($width, $height, function (Constraint $constraint) {
                $constraint->upsize();
                $constraint->aspectRatio();
            });
        }

        if ($this->isLocalOrPublic($disk)) {
            $image->save($destination);
            $image->destroy();
        } else {

            Storage::disk($disk)->put($destination, (string)$image->encode());
        }

        return true;
    }

    /**
     * generating video preview
     *
     * @param HCResource $resource
     * @param string $previewPath
     */
    protected function generateVideoPreview(HCResource $resource, string $previewPath)
    {
        $previewPath = storage_path('app/' . $previewPath);

        if (!file_exists($previewPath)) {
            mkdir($previewPath, 0755, true);
        }

        $videoPreview = $previewPath . '/preview_frame.jpg';

        if (!file_exists($videoPreview)) {
            $videoPath = storage_path('app/' . $resource->path);

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
                'timeout' => 3600, // The timeout for the underlying process
                'ffmpeg.threads' => 12,   // The number of threads that FFMpeg should use
            ]);

            /** @var Audio | Video $video */
            $video = $ffmpeg->open($videoPath);
            $duration = $video->getFFProbe()->format($videoPath)->get('duration');

            $images = rand(4, 7);
            $maxSize = 0;
            $maxIndex;
            for ($i = 0; $i < $images; $i++) {

                $video->frame(TimeCode::fromSeconds((int)($duration * ($i / $images))))
                    ->save($previewPath . "/video-$i.jpg");

                $size = filesize($previewPath . "/video-$i.jpg");

                if ($size > $maxSize) {
                    $maxSize = $size;
                    $maxIndex = $i;
                }
            }

            rename($previewPath . "/video-$maxIndex.jpg", $videoPreview);

            for ($i = 0; $i < $images; $i++) {
                if ($i !== $maxIndex) {
                    unlink($previewPath . "/video-$i.jpg");
                }
            }
        }
    }

    /**
     * @param $resource
     * @return string
     */
    protected function getOriginalFileName($resource): string
    {
        $name = str_replace($resource->extension, '', $resource->original_name);

        return str_slug($name) . $resource->extension;
    }

    /**
     * @param string $id
     * @param string $transpose
     */
    public function showRotatedVideo(string $id, string $transpose)
    {
        if (is_null($id)) {
            logger()->info('resourceId is null');
            exit;
        }

        $resource = $this->getRepository()->find($id);

        if (!$resource) {
            logger()->info('File record not found', ['id' => $id]);
            exit;
        }

        if ($resource->mime_type !== 'video/mp4') {
            logger()->info('Only videos can be rotated');
            exit;
        }

        $rotatedDir = storage_path('app/video-rotated/' . $transpose . '/');
        $rotatedFullPath = $rotatedDir . $id . '.mp4';

        if (!file_exists($rotatedFullPath)) {

            switch ($transpose) {
                case 1 :

                    $rotation = RotateFilter::ROTATE_90;
                    break;

                case 2 :

                    $rotation = RotateFilter::ROTATE_180;
                    break;

                case 3 :

                    $rotation = RotateFilter::ROTATE_270;
                    break;

                default:
                    logger()->info('Available options: 1,2,3');
                    exit;
            }

            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open(storage_path('app/' . $resource->path));
            //$video->filters()->custom('-qscale 0');
            $video->filters()->rotate($rotation);

            if (!file_exists($rotatedDir)) {
                mkdir($rotatedDir, 0755, true);
            }
            $video->save(new X264('aac'), $rotatedFullPath);
        }

        $resource->path = "video-rotated/$transpose/$id.mp4";

        $headers = [
            'Content-Type' => $resource->mime_type,
            'Content-Length' => Storage::disk($resource->disk)->size($resource->path),
        ];

        return Storage::disk($resource->disk)
            ->response($resource->path, $this->getOriginalFileName($resource), $headers);
    }

    /**
     * @param array $ids
     * @return array
     */
    public function forceDelete(array $ids): array
    {
        $deleted = $this->getRepository()->deleteForce($ids);
        $imagePreview = config('resources.image_preview');

        foreach ($deleted as $item) {
            if (isset($imagePreview)) {
                foreach ($imagePreview as $key => $img) {
                    $previewpath = 'preview' . DIRECTORY_SEPARATOR . $key . DIRECTORY_SEPARATOR . $item['id'] . '.jpg';
                    Storage::disk('public')->delete($previewpath);
                }
            }
            $this->removeResourceFromStorage($item, $item['disk']);
        }

        return $deleted;
    }

    /**
     * Remove item from storage
     *
     * @param HCResource $resource
     * @param string $disk
     */
    protected function removeResourceFromStorage(HCResource $resource, string $disk): void
    {
        if (Storage::disk($disk)->has($resource['path'])) {
            Storage::disk($disk)->delete($resource['path']);
        }
    }

    /**
     * Downloading and storing image in the system
     *
     * @param string $source
     * @param string|null $disk
     * @param string|null $customId
     * @param string|null $mimeType
     * @return array|null
     * @throws \Throwable
     */
    public function download(
        string $source,
        string $disk = null,
        string $customId = null,
        string $mimeType = null,
        string $ownerId = null
    ): ?array
    {
        // TODO maybe we should add exceptions instead of returning null values?
        if ($customId) {
            if ($resource = $this->getRepository()->find($customId)) {
                return $resource->toArray();
            }
        }

        print_r('downloading | ' . $customId . "\r\n");

        $fileName = $this->getFileName($source);

        if (!strlen($fileName)) {
            return null;
        }

        $this->createFolder('uploads/tmp', 'local');

        $destination = storage_path('app/uploads/tmp/' . $fileName);

        file_put_contents($destination, file_get_contents($source));

        if (filesize($destination) <= config('resources.max_checksum_size')) {
            $resource = $this->getRepository()->findOneBy(['checksum' => hash_file('sha256', $destination)]);

            if (!$this->allowDuplicates && $resource) {
                //If duplicate found deleting downloaded file
                unlink($destination);

                return $resource->toArray();
            }
        }

        if (!file_exists($destination)) {
            return null;
        }

        if (!$mimeType) {
            $mimeType = mime_content_type($destination);
        }

        $file = new UploadedFile($destination, $fileName, $mimeType, filesize($destination), null, true);

        //TODO change preservation / owner / disk locations
        return $this->upload($file, null, $ownerId, false, $disk, $customId);
    }

    /**
     * Retrieving file name
     *
     * @param $fileName
     * @return null|string
     * @throws \Throwable
     */
    protected function getFileName(string $fileName): ?string
    {
        if (!$fileName) {
            return null;
        }

        if (filter_var($fileName, FILTER_VALIDATE_URL)) {

            try {
                $headers = (get_headers($fileName));
            } catch (Throwable $exception) {
                report($exception);
                sleep(1);

                if ($this->headerCallAttempts < 5) {

                    $this->headerCallAttempts++;

                    return $this->getFileName($fileName);
                }

                throw $exception;
            }

            $this->headerCallAttempts = 0;

            foreach ($headers as $header) {
                if (strpos($header, 'filename')) {
                    $name = explode('filename=', $header);
                    $name = rtrim($name[1], '"');
                    $name = explode('.', $name);

                    if (sizeof($name) > 1) {
                        return str_slug(implode('.', $name));
                    }
                }
            }
        }

        $explodeFileURL = explode('/', $fileName);
        $fileName = end($explodeFileURL);

        $explodedByParams = explode('?', $fileName);
        $fileName = head($explodedByParams);

        $string = sanitizeString(pathinfo($fileName, PATHINFO_FILENAME));

        if (strpos($fileName, '.') !== false && substr($fileName, -3) != 'php') {
            return $string . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
        } else {
            return $string;
        }
    }

    /**
     * Create folder
     *
     * @param string $path
     * @param string $disk
     */
    protected function createFolder(string $path, string $disk): void
    {
        if (!Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->makeDirectory($path);
        }
    }

    /**
     * Upload and insert new resource into database
     * Catch for errors and if is error throw error
     *
     * @param UploadedFile $file
     * @param string|null $lastModified
     * @param string|null $owner_id
     * @param bool $preserve
     * @param string|null $disk
     * @param string|null $customId
     * @param array $previewSizes
     * @return array
     * @throws Throwable
     */
    public function upload(
        UploadedFile $file,
        string $lastModified = null,
        string $owner_id = null,
        bool $preserve = false,
        string $disk = null,
        string $customId = null,
        array $previewSizes = []
    ): array
    {
        try {
            if (is_null($disk)) {
                $disk = config('resources.upload_disk');
            }

            /** @var HCResource $resource */
            $resource = $this->getRepository()->create(
                $this->getFileParams($file, $disk, $lastModified, $owner_id, $customId)
            );

            $this->saveInStorage($resource, $file);
            $this->createPreviewThumb($resource->id, $resource->path, $resource->mime_type, $disk, $previewSizes);

            // generate checksum
            if ($resource->size <= config('resources.max_checksum_size')) {
                $this->getRepository()->updateChecksum($resource);
            }
        } catch (\Throwable $exception) {
            if (isset($resource)) {
                $this->removeResource($disk, $resource);
            }

            throw $exception;
        }

        $resource = $resource->toArray();
        // set storage resource url
        $resource['storageUrl'] = $this->isLocalOrPublic($disk) ? null : Storage::disk($disk)->url($resource['path']);

        return $resource;
    }

    /**
     * Get file params
     * @param UploadedFile $file
     * @param string $disk
     * @param string|null $lastModified
     * @param string|null $customId
     * @return array
     */
    public function getFileParams(
        UploadedFile $file,
        string $disk,
        string $lastModified = null,
        string $ownerId = null,
        string $customId = null
    ): array
    {
        $params = [];

        if ($customId) {
            $params['id'] = $customId;
        } else {
            $params['id'] = Uuid::uuid4()->toString();
        }

        // TODO test with .svg
        $extension = $this->getExtension($file);

        // TODO add extension to original when original name is not well formed
        $params['disk'] = $disk;
        $params['original_name'] = $file->getClientOriginalName();
        $params['extension'] = $extension;
        $params['safe_name'] = $params['id'] . $extension;
        $params['path'] = $this->uploadPath . $params['safe_name'];
        $params['size'] = $file->getSize();
        $params['mime_type'] = $file->getClientMimeType();
        $params['uploaded_by'] = auth()->check() ? auth()->id() : null;
        // TODO move lastModified getting to upload method as param
        $params['original_at'] = $lastModified;
        $params['owner_id'] = $ownerId;

        return $params;
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    protected function getExtension(UploadedFile $file): string
    {
        if (!$extension = $file->getClientOriginalExtension()) {
            $extension = explode('/', $file->getClientMimeType())[1];
        }

        return '.' . $extension;
    }

    /**
     * Upload file to server
     *
     * @param HCResource $resource
     * @param UploadedFile $file
     */
    protected function saveInStorage(HCResource $resource, UploadedFile $file): void
    {
        if (Storage::disk($resource->disk)->exists($resource->path)) {
            logger()->warning('file already exists with this name: ' . $resource->safe_name);

            return;
        }

        $file->storeAs($this->uploadPath, $resource->safe_name, $resource->disk);
    }

    /**
     * @param string $id
     * @param string $resourcePath
     * @param string $mimeType
     * @param string $disk
     * @param array $previewSizes
     * @param bool $addToQueue
     */
    public function createPreviewThumb(
        string $id,
        string $resourcePath,
        string $mimeType,
        string $disk,
        array $previewSizes = [],
        bool $addToQueue = false
    ): void
    {
        if (config('resources.resize_original') === true && $this->isValidForPreviewThumb($mimeType) && Storage::disk($disk)->exists($resourcePath)) {
            //set resize params
            $originalWidth = config('resources.original_dimensions.width');
            $originalHeight = config('resources.original_dimensions.height');
            $originalQuality = config('resources.original_dimensions.quality');
            if (filter_var($originalWidth, FILTER_VALIDATE_INT) === false)
                $originalWidth = 1920;
            if (filter_var($originalHeight, FILTER_VALIDATE_INT) === false)
                $originalWidth = 1080;
            if (filter_var($originalQuality, FILTER_VALIDATE_INT) === false)
                $originalQuality = 100;

            //resize
            $path = config('filesystems.disks.' . $disk . '.root');
            $source = $path . DIRECTORY_SEPARATOR . $resourcePath;

            $image = Image::make($source);

            $image->resize($originalWidth, $originalHeight, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $image->save($source, $originalQuality);
        }

        $imagePreview = config('resources.image_preview');

        if (isset($imagePreview) && $this->isValidForPreviewThumb($mimeType) && Storage::disk($disk)->exists($resourcePath)) {
            $path = config('filesystems.disks.' . $disk . '.root');

            $destinationPath = storage_path('app/public/preview');
            $source = $path . DIRECTORY_SEPARATOR . $resourcePath;

            if ($addToQueue) {
                $itemsInstant = [];

                $itemsJob = array_where($imagePreview, function ($value, $key) use ($previewSizes) {
                    return ($value['default'] || $previewSizes && in_array($value['width'] . 'x' . $value['height'],
                            $previewSizes));
                });
            } else {
                $itemsInstant = array_where($imagePreview, function ($value, $key) use ($previewSizes) {
                    return ($value['generate'] && ($value['default'] ||
                            $previewSizes && in_array($value['width'] . 'x' . $value['height'], $previewSizes)));
                });

                $itemsJob = array_where($imagePreview, function ($value, $key) use ($previewSizes) {
                    return (!$value['generate'] && ($value['default'] ||
                            $previewSizes && in_array($value['width'] . 'x' . $value['height'], $previewSizes)));
                });
            }

            if ($itemsInstant) {
                $this->generatePreviewThumb($id, $itemsInstant, $destinationPath, $source);
            }
            if ($itemsJob) {
                ProcessPreviewImage::dispatch($id, $itemsJob, $destinationPath, $source);
            }
        }
    }

    /**
     * @param string $mimeType
     * @return bool
     */
    private function isValidForPreviewThumb(string $mimeType): bool
    {
        $validMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

        if (in_array($mimeType, $validMimeTypes)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $resourceId
     * @param array $items
     * @param string $destination
     * @param string $source
     */
    public function generatePreviewThumb(string $resourceId, array $items, string $destination, string $source): void
    {
        foreach ($items as $item) {
            $previewWidth = $item['width'];
            $previewHeight = $item['height'];
            $previewQuality = $item['quality'];

            $destinationPath = $destination . DIRECTORY_SEPARATOR . $previewWidth . 'x' . $previewHeight;
            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $destinationPath .= DIRECTORY_SEPARATOR . $resourceId . '.jpg';

            /** @var \Intervention\Image\Image $image */
            $image = Image::make($source);

            if ($image->width() < $image->height()) {
                $scale = $image->width() / $previewWidth;
            } else {
                $scale = $image->height() / $previewHeight;
            }

            $width = (integer)($previewWidth * $scale);
            $height = (integer)($previewHeight * $scale);

            if ($width > $image->width()) {
                $scale = $image->width() / $previewWidth;

                $width = (integer)($previewWidth * $scale);
                $height = (integer)($previewHeight * $scale);
            }

            $x = ($image->width() - $width) * 0.5;
            $y = ($image->height() - $height) * 0.5;

            $image->crop($width, $height, (integer)($x), (integer)($y));
            $image->resize($previewWidth, $previewHeight);

            $image->save($destinationPath, $previewQuality);
            $image->destroy();
        }
    }

    /**
     * @param string $disk
     * @param $resource
     */
    protected function removeResource(string $disk, $resource): void
    {
        $this->removeResourceFromStorage($resource, $disk);

        if ($resource instanceof HCResource) {
            $resource->forceDelete();
        }
    }

    /**
     * @param string $id
     * @return Model|null
     */
    public function getById(string $id)
    {
        return $this->getRepository()->makeQuery()->findOrFail($id);
    }

    /**
     * @param HCResourceRequest $request
     * @return mixed
     */
    public function getListPaginate(HCResourceRequest $request)
    {
        $data = $this->getRepository()->getListPaginate($request);

        $data->transform(function (HCResource $model) {

            return (new ResourceDTO())
                ->setModel($model)->jsonDataList();
        });

        return $data;
    }

    /**
     * @param string $path - uploads/date/file-name
     * @param string $disk
     * @return string
     */
    protected function getResourcePath(string $path, string $disk): string
    {
        if ($this->isLocal($disk)) {
            return config('filesystems.disks.local.root') . DIRECTORY_SEPARATOR . $path;
        }

        return Storage::disk($disk)->url($path);
    }

    /**
     * @param string $disk
     * @return bool
     */
    protected function isLocal(string $disk): bool
    {
        return $disk == 'local';
    }

    /**
     * @param string $disk
     * @return bool
     */
    protected function isPublic(string $disk): bool
    {
        return $disk == 'public';
    }
}
