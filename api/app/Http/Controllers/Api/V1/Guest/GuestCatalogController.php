<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestCatalogController extends Controller
{
    public function services(Request $request): JsonResponse
    {
        $hotel = $request->attributes->get('guestHotel');
        $services = Service::query()
            ->with(['category', 'department'])
            ->where('hotel_id', $hotel->id)
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'kind' => $service->category->kind->value,
                'category' => $service->category->name,
                'fee_minor' => (int) $service->fee_minor,
                'fee_display' => Money::format((int) $service->fee_minor, $hotel->currency),
                'target_minutes' => $service->target_minutes,
                'department' => $service->department->name,
            ])->values(),
        ]);
    }

    public function menu(Request $request): JsonResponse
    {
        $hotel = $request->attributes->get('guestHotel');
        $categories = MenuCategory::query()
            ->with(['items' => fn ($query) => $query->where('is_available', true)->orderBy('sort_order')->orderBy('name')])
            ->where('hotel_id', $hotel->id)
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'items' => $category->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price_minor' => (int) $item->price_minor,
                    'price_display' => Money::format((int) $item->price_minor, $hotel->currency),
                    'image_url' => $item->image_path ? url('/storage/'.$item->image_path) : null,
                    'is_sold_out' => (bool) $item->is_sold_out,
                    'dietary_info' => $item->dietary_info,
                ])->values(),
            ])->values(),
        ]);
    }
}
