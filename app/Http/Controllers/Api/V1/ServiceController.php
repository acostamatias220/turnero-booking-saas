<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        return ServiceResource::collection(Service::query()->with('staff')->orderBy('name')->get());
    }
}
