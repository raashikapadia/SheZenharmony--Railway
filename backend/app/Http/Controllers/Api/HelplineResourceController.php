<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HelplineResource;
use Illuminate\Http\JsonResponse;

class HelplineResourceController extends Controller
{
    /** The published helplines shown in the student Resource tab. */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => HelplineResource::query()
                ->visible()
                ->inDisplayOrder()
                ->get()
                ->map(fn (HelplineResource $resource): array => [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'organisation' => $resource->organisation,
                    'description' => $resource->description,
                    'phone' => $resource->phone,
                    'alternate_phone' => $resource->alternate_phone,
                    'email' => $resource->email,
                    'website_url' => $resource->website_url,
                    'availability' => $resource->availability,
                    'category' => $resource->category,
                    'is_emergency' => $resource->is_emergency,
                ])->values(),
        ]);
    }
}
