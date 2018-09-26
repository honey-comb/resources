<?php

declare(strict_types = 1);

namespace HoneyComb\Resources\Services\Admin;

use HoneyComb\Resources\Jobs\ProcessImageThumbnail;
use HoneyComb\Resources\Repositories\Admin\HCResourceGrabPropertyRepository;
use HoneyComb\Resources\Services\HCResourceService;
use HoneyComb\Resources\Services\HCResourceThumbService;
use Intervention\Image\Facades\Image;

class HCResourceGrabPropertyService
{
    /**
     * @var HCResourceGrabPropertyRepository
     */
    private $repository;
    /**
     * @var \HoneyComb\Resources\Services\HCResourceService
     */
    private $resourceService;
    /**
     * @var \HoneyComb\Resources\Services\HCResourceThumbService
     */
    private $thumbService;

    /**
     * HCResourceGrabPropertyService constructor.
     * @param HCResourceGrabPropertyRepository $repository
     * @param \HoneyComb\Resources\Services\HCResourceService $resourceService
     * @param \HoneyComb\Resources\Services\HCResourceThumbService $thumbService
     */
    public function __construct(
        HCResourceGrabPropertyRepository $repository,
        HCResourceService $resourceService,
        HCResourceThumbService $thumbService
    ) {
        $this->repository = $repository;
        $this->resourceService = $resourceService;
        $this->thumbService = $thumbService;
    }

    /**
     * @return HCResourceGrabPropertyRepository
     */
    public function getRepository(): HCResourceGrabPropertyRepository
    {
        return $this->repository;
    }

    /**
     * @param array $images
     */
    public function generateThumbs(array $images): void
    {
        $resources = $this->resourceService->getRepository()
            ->makeQuery()
            ->setEagerLoads([])
            ->whereIn('id', array_pluck($images, 'resource_id'))
            ->get();

        $thumbnails = $this->thumbService->getRepository()
            ->makeQuery()
            ->setEagerLoads([])
            ->whereIn('id', array_pluck($images, 'thumbnail_id'))
            ->get();

        foreach ($images as $image) {
            $this->generateThumbnail($image, $resources, $thumbnails);
        }
    }

    /**
     * @param array $image
     * @param $resources
     * @param $thumbnails
     */
    public function generateThumbnail(array $image, $resources, $thumbnails): void
    {
        $searchArray = [
            'resource_id' => $image['resource_id'],
            'source_type' => $image['source_type'],
            'source_id' => $image['source_id'],
            'thumbnail_id' => $image['thumbnail_id'],
        ];

        $record = $this->repository->findOneBy($searchArray);

        if (is_null($record)) {

            $resource = optional($resources->where('id', $image['resource_id'])->first())->toArray();
            $thumb = optional($thumbnails->where('id', $image['thumbnail_id'])->first())->toArray();

            if ($resource && $thumb) {
                $grabProperty = $this->repository->create($image);
                ProcessImageThumbnail::dispatch($grabProperty->toArray(), $resource, $thumb);
            }
        } elseif (!($record->x == $image['x'] && $record->y == $image['y'])) {
            $resource = optional($resources->where('id', $image['resource_id'])->first())->toArray();
            $thumb = optional($thumbnails->where('id', $image['thumbnail_id'])->first())->toArray();

            if ($resource && $thumb) {
                ProcessImageThumbnail::dispatch($image, $resource, $thumb);

                $this->repository->updateOrCreate($searchArray, $image);
            }
        }
    }
}
