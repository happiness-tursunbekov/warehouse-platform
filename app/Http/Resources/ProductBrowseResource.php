<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBrowseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'onHand' => $this->onHand,
            'onHandAvailable' => $this->onHandAvailable,
            'inactiveFlag' => $this->inactiveFlag,
            'files' => []
        ];
    }
}
