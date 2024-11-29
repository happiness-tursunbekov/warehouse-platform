<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'project' => $this->project,
            'team' => $this->team,
            'customer' => new CustomerResource($this->customer),
            'preparedBy' => new CustomerResource($this->preparedBy),
            'acceptedByMember' => $this->acceptedByMember,
            'signature' => $this->signature,
            'status' => $this->status,
            'items' => OrderItemResource::collection($this->items),
            'totalCost' => $this->totalCost,
            'createdAt' => $this->createdAt->format('m/d/Y H:i')
        ];
    }
}
