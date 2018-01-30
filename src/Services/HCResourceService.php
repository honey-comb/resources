<?php
declare(strict_types = 1);

namespace HoneyComb\Resources\Services;

use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use File;
use HoneyComb\Resources\Models\HCResource;
use HoneyComb\Resources\Repositories\HCResourceRepository;
use Illuminate\Http\UploadedFile;
use Image;
use Intervention\Image\Constraint;
use Ramsey\Uuid\Uuid;
use Storage;


/**
 * Class HCResourceService
 * @package HoneyComb\Resources\Services
 */
class HCResourceService
{
    /**
     * Maximum file size to perform checksum calculation
     */
    const MAX_CHECKSUM_SIZE = 102400000;

    /**
     * File upload location
     *
     * @var string
     */
    private $uploadPath;

    /**
     * If uploaded file has predefined ID it will be used
     *
     * @var
     */
    private $resourceId;

    /**
     * @var bool
     */
    private $allowDuplicates;

    /**
     * @var HCResourceRepository
     */
    private $repository;

    /**
     * HCResourceService constructor.
     *
     * @param HCResourceRepository $repository
     * @param bool $allowDuplicates - should the checksum be validated and duplicates found
     */
    public function __construct(HCResourceRepository $repository, bool $allowDuplicates = false)
    {
        $this->allowDuplicates = $allowDuplicates;

        $this->uploadPath = 'uploads/' . date("Y-m-d") . DIRECTORY_SEPARATOR;
        $this->repository = $repository;
    }

    /**
     * @return HCResourceRepository
     */
    public function getRepository(): HCResourceRepository
    {
        return $this->repository;
    }

    /**
     * @param null|string $id
     * @param int $width
     * @param int $height
     * @param bool $fit
     */
    public function show(?string $id, int $width, int $height, bool $fit): void
    {
        if (is_null($id)) {
            logger()->info('resourceId is null');
            exit;
        }

        $storagePath = storage_path('app/');

        // cache resource for 10 days
        $resource = \Cache::remember($id, 14400, function () use ($id) {
            return HCResource::find($id);
        });

        if (!$resource) {
            logger()->info('File record not found', ['id' => $id]);
            exit;
        }

        if (!Storage::exists($resource->path)) {
            logger()->info('File not found in storage', ['id' => $id, 'path' => $resource->path]);
            exit;
        }

        $cachePath = $this->generateResourceCacheLocation($resource->id, $width, $height, $fit) . $resource->extension;

        if (file_exists($cachePath)) {
            $resource->size = File::size($cachePath);
            $resource->path = $cachePath;
        } else {

            switch ($resource->mime_type) {
                case 'text/plain' :
                    if ($resource->extension == '.svg') {

                        $resource->mime_type = 'image/svg+xml';
                    }
                case 'image/png' :
                case 'image/jpeg' :
                case 'image/jpg' :

                    if ($width != 0 && $height != 0) {

                        $this->createImage($storagePath . $resource->path, $cachePath, $width, $height, $fit);

                        $resource->size = File::size($cachePath);
                        $resource->path = $cachePath;
                    } else {
                        $resource->path = $storagePath . $resource->path;
                    }
                    break;

                case 'video/mp4' :

                    $previewPath = str_replace('-', '/', $resource->id);
                    $fullPreviewPath = $storagePath . 'video-previews/' . $previewPath;

                    $cachePath = $this->generateResourceCacheLocation($previewPath, $width, $height, $fit) . '.jpg';

                    if (file_exists($cachePath)) {
                        $resource->size = File::size($cachePath);
                        $resource->path = $cachePath;
                        $resource->mime_type = 'image/jpg';
                    } else {

                        if ($width != 0 && $height != 0) {

                            $videoPreview = $fullPreviewPath . '/preview_frame.jpg';

                            //TODO: generate 3-5 previews and take the one with largest size
                            $this->generateVideoPreview($resource, $storagePath, $previewPath);

                            $this->createImage($videoPreview, $cachePath, $width, $height, $fit);

                            $resource->size = File::size($cachePath);
                            $resource->path = $cachePath;
                            $resource->mime_type = 'image/jpg';
                        } else {
                            $resource->path = $storagePath . $resource->path;
                        }
                    }
                    break;

                default:

                    $resource->path = $storagePath . $resource->path;
                    break;
            }
        }

        // Show resource
        header('Pragma: public');
        header('Cache-Control: max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
        header('Content-Length: ' . $resource->size);
        header('Content-Disposition: inline;filename="' . $resource->original_name . '"');
        header('Content-Type: ' . $resource->mime_type);
        readfile($resource->path);

        exit;
    }

    /**
     * Upload and insert new resource into database
     * Catch for errors and if is error throw error
     *
     * @param UploadedFile $file
     * @param bool $full
     * @param string $id
     * @return mixed
     * @throws \Exception
     */
    public function upload(UploadedFile $file, bool $full = null, string $id = null)
    {
        if (is_null($file)) {
            throw new \Exception(trans('resources::resources.errors.no_resource_selected'));
        }

        $this->resourceId = $id;

        try {
            $resource = $this->repository->create(
                $this->getFileParams($file)
            );

            $this->saveResourceInStorage($resource, $file);

            // generate checksum
            if ($resource['size'] <= config('resources.max_checksum_size', self::MAX_CHECKSUM_SIZE)) {
                $this->repository->update(
                    [
                        'checksum' => hash_file('sha256', storage_path('app/' . $resource['path'])),
                    ],
                    $resource->id
                );
            }

//            Artisan::call('hc:generate-thumbs', ['id' => $resource->id]);
        } catch (\Exception $e) {

            if (isset($resource)) {
                $this->removeImageFromStorage($resource);
            }

            throw $e;
        }

        if ($full) {
            return $resource->toArray();
        }

        return [
            'id' => $resource->id,
            'url' => route('resource.get', $resource->id),
        ];
    }

    /**
     * Downloading and storing image in the system
     *
     * @param string $source
     * @param bool $full - if set to true than return full resource data
     * @param string $id
     * @param null|string $mime_type
     * @return array|mixed|null
     * @throws \Exception
     */
    public function download(string $source, bool $full = null, string $id = null, string $mime_type = null)
    {
        $this->createFolder('uploads/tmp');

        $fileName = $this->getFileName($source);

        if ($fileName && $fileName != '') {

            $destination = storage_path('app/uploads/tmp/' . $fileName);

            file_put_contents($destination, file_get_contents($source));

            if (filesize($destination) <= config('resources.max_checksum_size', self::MAX_CHECKSUM_SIZE)) {
                $resource = $this->repository->findOneBy(['checksum' => hash_file('sha256', $destination)]);

                if (!$this->allowDuplicates && $resource) {
                    //If duplicate found deleting downloaded file
                    \File::delete($destination);

                    if ($full) {
                        return $resource->toArray();
                    }

                    return [
                        'id' => $resource->id,
                        'url' => route('resource.get', $resource->id),
                    ];
                }
            }

            if (!\File::exists($destination)) {
                return null;
            }

            if (!$mime_type) {
                $mime_type = mime_content_type($destination);
            }

            $file = new UploadedFile($destination, $fileName, $mime_type, filesize($destination), null, true);

            return $this->upload($file, $full, $id);
        }

        return null;
    }

    /**
     * Get file params
     *
     * @param UploadedFile $file
     * @return array
     */
    public function getFileParams(UploadedFile $file)
    {
        $params = [];

        if ($this->resourceId) {
            $params['id'] = $this->resourceId;
        } else {
            $params['id'] = Uuid::uuid4()->toString();
        }

        // TODO test with .svg
        $extension = $this->getExtension($file);

        // TODO add extension to original when original name is not well formed
        $params['original_name'] = $file->getClientOriginalName();
        $params['extension'] = $extension;
        $params['safe_name'] = $params['id'] . $extension;
        $params['path'] = $this->uploadPath . $params['safe_name'];
        $params['size'] = $file->getClientSize();
        $params['mime_type'] = $file->getClientMimeType();
        $params['uploaded_by'] = auth()->check() ? auth()->id() : null;

        return $params;
    }

    /**
     * Upload file to server
     *
     * @param $resource
     * @param $file
     */
    protected function saveResourceInStorage(HCResource $resource, UploadedFile $file): void
    {
        $this->createFolder($this->uploadPath);

        $file->move(
            storage_path('app/' . $this->uploadPath),
            $resource->id . $this->getExtension($file)
        );
    }

    /**
     * Remove item from storage
     *
     * @param HCResource $resource
     */
    protected function removeImageFromStorage(HCResource $resource): void
    {
        $path = $this->uploadPath . $resource->id;

        if (Storage::has($path)) {
            Storage::delete($path);
        }
    }

    /**
     * Create folder
     *
     * @param $path
     */
    protected function createFolder(string $path): void
    {
        if (!Storage::exists($path)) {
            Storage::makeDirectory($path);
        }
    }

    /**
     * Retrieving file name
     *
     * @param $fileName
     * @return null|string
     */
    protected function getFileName(string $fileName): ? string
    {
        if (!$fileName && filter_var($fileName, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $explodeFileURL = explode('/', $fileName);
        $fileName = end($explodeFileURL);

        $explodedByParams = explode('?', $fileName);
        $fileName = head($explodedByParams);

        return sanitizeString(pathinfo($fileName, PATHINFO_FILENAME)) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
    }

    /**
     * generating video preview
     *
     * @param HCResources $resource
     * @param string $storagePath
     * @param string $previewPath
     */
    private function generateVideoPreview(HCResources $resource, string $storagePath, string $previewPath): void
    {
        $fullPreviewPath = $storagePath . 'video-previews/' . $previewPath;

        if (!file_exists($fullPreviewPath)) {
            mkdir($fullPreviewPath, 0755, true);
        }

        $videoPreview = $fullPreviewPath . '/preview_frame.jpg';

        if (!file_exists($videoPreview)) {

            $videoPath = $storagePath . $resource->path;

            $ffmpeg = FFMpeg::create([
                'ffmpeg.binaries' => '/usr/bin/ffmpeg',
                'ffprobe.binaries' => '/usr/bin/ffprobe',
                'timeout' => 3600, // The timeout for the underlying process
                'ffmpeg.threads' => 12,   // The number of threads that FFMpeg should use
            ]);

            $video = $ffmpeg->open($storagePath . $resource->path);
            $duration = $video->getFFProbe()->format($videoPath)->get('duration');

            $video->frame(TimeCode::fromSeconds(rand(1, $duration)))
                ->save($videoPreview);

            $resource->mime_type = 'image/jpg';
            $resource->path = $videoPreview;
        }
    }

    /**
     * Generating resource cache location and name
     *
     * @param $id
     * @param int|null $width
     * @param int|null $height
     * @param null $fit
     * @return string
     */
    private function generateResourceCacheLocation($id, $width = 0, $height = 0, $fit = null): string
    {
        $path = storage_path('app/') . 'cache/' . str_replace('-', '/', $id) . '/';

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $path .= $width . '_' . $height;

        if ($fit) {
            $path .= '_fit';
        }

        return $path;
    }

    /**
     * Creating image based on provided data
     *
     * @param $source
     * @param $destination
     * @param int $width
     * @param int $height
     * @param bool $fit
     * @return bool
     */
    private function createImage($source, $destination, $width = 0, $height = 0, $fit = false): bool
    {
        if ($width == 0) {
            $width = null;
        }

        if ($height == 0) {
            $height = null;
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

        $image->save($destination);

        return true;
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    private function getExtension(UploadedFile $file): string
    {
        if (!$extension = $file->getClientOriginalExtension()) {
            $extension = '.' . explode('/', $file->getClientMimeType())[1];
        }

        return $extension;
    }
}
