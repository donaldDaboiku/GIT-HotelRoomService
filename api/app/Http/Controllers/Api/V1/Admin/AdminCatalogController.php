<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ServiceKind;
use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\AuditLogger;
use App\Services\ImageGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminCatalogController extends Controller
{
    public function serviceCategories(Request $request): JsonResponse
    {
        $rows = ServiceCategory::query()->where('hotel_id', $request->user()->hotel_id)->orderBy('sort_order')->get();

        return response()->json(['data' => $rows]);
    }

    public function storeServiceCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('service_categories', 'name')->where('hotel_id', $request->user()->hotel_id)],
            'kind' => ['required', Rule::enum(ServiceKind::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $category = ServiceCategory::query()->create([
            ...$data,
            'hotel_id' => $request->user()->hotel_id,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return response()->json(['data' => $category], 201);
    }

    public function services(Request $request): JsonResponse
    {
        $rows = Service::query()->with(['category', 'department'])->where('hotel_id', $request->user()->hotel_id)->orderBy('sort_order')->get();

        return response()->json(['data' => $rows->map(fn (Service $service) => $this->servicePayload($service))]);
    }

    public function storeService(Request $request): JsonResponse
    {
        $service = Service::query()->create($this->serviceData($request));
        AuditLogger::record($request->user(), 'service.created', $service, [], $request->ip(), $service->hotel_id);

        return response()->json(['data' => $this->servicePayload($service->load(['category', 'department']))], 201);
    }

    public function updateService(Request $request, int $service): JsonResponse
    {
        $model = $this->service($request, $service);
        $model->fill($this->serviceData($request))->save();
        AuditLogger::record($request->user(), 'service.updated', $model, [], $request->ip(), $model->hotel_id);

        return response()->json(['data' => $this->servicePayload($model->load(['category', 'department']))]);
    }

    public function menuCategories(Request $request): JsonResponse
    {
        return response()->json([
            'data' => MenuCategory::query()->where('hotel_id', $request->user()->hotel_id)->orderBy('sort_order')->get(),
        ]);
    }

    public function storeMenuCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('menu_categories', 'name')->where('hotel_id', $request->user()->hotel_id)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_available' => ['required', 'boolean'],
        ]);
        $category = MenuCategory::query()->create([...$data, 'hotel_id' => $request->user()->hotel_id, 'sort_order' => $data['sort_order'] ?? 0]);

        return response()->json(['data' => $category], 201);
    }

    public function menuItems(Request $request): JsonResponse
    {
        $rows = MenuItem::query()->with('category')->where('hotel_id', $request->user()->hotel_id)->orderBy('sort_order')->get();

        return response()->json(['data' => $rows->map(fn (MenuItem $item) => $this->itemPayload($item))]);
    }

    public function storeMenuItem(Request $request): JsonResponse
    {
        $item = MenuItem::query()->create($this->itemData($request));
        AuditLogger::record($request->user(), 'menu.created', $item, ['price_minor' => (int) $item->price_minor], $request->ip(), $item->hotel_id);

        return response()->json(['data' => $this->itemPayload($item->load('category'))], 201);
    }

    public function updateMenuItem(Request $request, int $item): JsonResponse
    {
        $model = $this->item($request, $item);
        $model->fill($this->itemData($request))->save();
        AuditLogger::record($request->user(), 'menu.updated', $model, ['price_minor' => (int) $model->price_minor], $request->ip(), $model->hotel_id);

        return response()->json(['data' => $this->itemPayload($model->load('category'))]);
    }

    public function menuImage(Request $request, int $item): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:2048']]);
        $model = $this->item($request, $item);
        $extension = ImageGuard::extension($request->file('image'), 'image');
        $path = $request->file('image')->storeAs('menu', Str::uuid().'.'.$extension, 'public');
        $model->forceFill(['image_path' => $path])->save();

        return response()->json(['data' => $this->itemPayload($model->load('category'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceData(Request $request): array
    {
        $hotelId = $request->user()->hotel_id;
        $data = $request->validate([
            'service_category_id' => ['required', 'integer', Rule::exists('service_categories', 'id')->where('hotel_id', $hotelId)],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('hotel_id', $hotelId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_available' => ['required', 'boolean'],
            'target_minutes' => ['nullable', 'integer', 'between:1,10080'],
            'fee_minor' => ['required', 'integer', 'between:0,100000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [...$data, 'hotel_id' => $hotelId, 'sort_order' => $data['sort_order'] ?? 0];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemData(Request $request): array
    {
        $hotelId = $request->user()->hotel_id;
        $data = $request->validate([
            'menu_category_id' => ['required', 'integer', Rule::exists('menu_categories', 'id')->where('hotel_id', $hotelId)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_minor' => ['required', 'integer', 'between:0,100000000'],
            'is_available' => ['required', 'boolean'],
            'is_sold_out' => ['required', 'boolean'],
            'dietary_info' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        return [...$data, 'hotel_id' => $hotelId, 'sort_order' => $data['sort_order'] ?? 0];
    }

    private function service(Request $request, int $id): Service
    {
        $service = Service::query()->where('hotel_id', $request->user()->hotel_id)->whereKey($id)->first();
        abort_if($service === null, 404);

        return $service;
    }

    private function item(Request $request, int $id): MenuItem
    {
        $item = MenuItem::query()->where('hotel_id', $request->user()->hotel_id)->whereKey($id)->first();
        abort_if($item === null, 404);

        return $item;
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePayload(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'description' => $service->description,
            'is_available' => (bool) $service->is_available,
            'target_minutes' => $service->target_minutes,
            'fee_minor' => (int) $service->fee_minor,
            'sort_order' => (int) $service->sort_order,
            'category' => ['id' => $service->category->id, 'name' => $service->category->name, 'kind' => $service->category->kind->value],
            'department' => ['id' => $service->department->id, 'name' => $service->department->name, 'code' => $service->department->code->value],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'price_minor' => (int) $item->price_minor,
            'image_url' => $item->image_path ? url('/storage/'.$item->image_path) : null,
            'is_available' => (bool) $item->is_available,
            'is_sold_out' => (bool) $item->is_sold_out,
            'dietary_info' => $item->dietary_info,
            'sort_order' => (int) $item->sort_order,
            'category' => ['id' => $item->category->id, 'name' => $item->category->name],
        ];
    }
}
