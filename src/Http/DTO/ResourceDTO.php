<?php

namespace HoneyComb\Resources\Http\DTO;

use HoneyComb\Resources\Models\HCResource;
use HoneyComb\Starter\DTO\HCBaseDTO;

class ResourceDTO extends HCBaseDTO {

    /**
     * @param HCResource $resource
     * @return $this
     */
    public function setModel(HCResource $resource)
    {
        $this->id = $resource->id;
        $this->original_name = $resource->original_name;
        $this->preserve = $resource->preserve;

        return $this;
    }

    /**
     * @return array
     */
    public function jsonData(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'preserve' => boolval($this->preserve),
        ];
    }

    /**
     * @return array
     */
    public function jsonDataList (): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'preserve' => boolval($this->preserve),
        ];
    }
}
