<?php

namespace App\Http\Resources;

use App\Traits\PaginationCamelCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderCollectionResource extends JsonResource
{
    use PaginationCamelCase;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project' => $this->project,
            'team' => $this->team,
            'customer' => new CustomerResource($this->customer),
            'status' => $this->status,
            'itemsCount' => $this->items()->count(),
            'totalCost' => $this->totalCost,
            'createdAt' => $this->createdAt->format('m/d/Y H:i')
        ];
    }
}
