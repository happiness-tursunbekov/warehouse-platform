<?php

namespace App\Traits;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

trait PaginationCamelCase
{
    protected static function newCollection($resource)
    {
        return new class ($resource, static::class) extends ResourceCollection {
            public function __construct($resource, $collects)
            {
                $this->collects = $collects;

                parent::__construct($resource);
            }

            protected function preparePaginatedResponse($request)
            {
                if ($this->preserveAllQueryParameters) {
                    $this->resource->appends($request->query());
                } elseif (! is_null($this->queryParameters)) {
                    $this->resource->appends($this->queryParameters);
                }

                return (new class ($this) extends PaginatedResourceResponse {
                    protected function paginationInformation($request)
                    {
                        $paginated = $this->resource->resource->toArray();

                        $default = [
                            'meta' => [
                                'total' => $paginated['total'],
                                'currentPage' => $paginated['current_page'],
                                'perPage' => $paginated['per_page'],
                                'totalPages' => $paginated['last_page']
                            ],
                        ];

                        if (method_exists($this->resource, 'paginationInformation') ||
                            $this->resource->hasMacro('paginationInformation')) {
                            return $this->resource->paginationInformation($request, $paginated, $default);
                        }

                        return $default;
                    }
                })->toResponse($request);
            }
        };
    }
}
