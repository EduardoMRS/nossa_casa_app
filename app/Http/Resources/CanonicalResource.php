<?php

namespace App\Http\Resources;

use App\Data\CanonicalData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CanonicalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CanonicalData $data */
        $data = $this->resource;

        return $data->toArray();
    }
}
