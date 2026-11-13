<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SuperAdminOrderServiceController extends Controller
{
    public function storeService(Request $request): JsonResponse
    {
        $data = $this->serviceData($request);
        $service = DB::transaction(function () use ($request, $data): Service {
            $service = Service::query()->create($data + ['slug' => $this->uniqueSlug($data['name'])]);
            AuditLog::record($request->user(), 'service.created', $service, [], $service->only(['name', 'price', 'currency', 'status']));

            return $service;
        });

        return response()->json(['id' => $service->id], 201);
    }

    public function updateService(Request $request, Service $service): JsonResponse
    {
        $data = $this->serviceData($request, $service);
        DB::transaction(function () use ($request, $data, $service): void {
            $old = $service->only(['name', 'description', 'price', 'currency', 'status', 'is_featured']);
            if ($service->name !== $data['name']) {
                $data['slug'] = $this->uniqueSlug($data['name'], $service->id);
            }
            $service->fill($data)->save();
            AuditLog::record($request->user(), 'service.updated', $service, $old, $service->only(array_keys($old)));
        });

        return response()->json(['id' => $service->id, 'status' => $service->status]);
    }

    public function updateOrder(Request $request, Order $order): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Order::STATUSES)],
            'assigned_staff_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('account_type', [
                User::ACCOUNT_TYPE_ADMINISTRATOR,
                User::ACCOUNT_TYPE_SUPER_ADMIN,
            ])],
            'fulfillment_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::transaction(function () use ($request, $data, $order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $old = $locked->only(['status', 'assigned_staff_id', 'fulfillment_notes']);
            $locked->fill($data)->save();
            AuditLog::record($request->user(), 'order.fulfillment_updated', $locked, $old, $locked->only(array_keys($old)));
        });

        return response()->json(['id' => $order->id, 'status' => $order->fresh()->status]);
    }

    private function serviceData(Request $request, ?Service $service = null): array
    {
        $nameRule = Rule::unique('services', 'name');
        if ($service) {
            $nameRule->ignore($service->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $nameRule],
            'description' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::in(['available', 'unavailable'])],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);
    }

    private function uniqueSlug(string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name) ?: 'service';
        $slug = $base;
        $suffix = 2;
        while (Service::withTrashed()->where('slug', $slug)->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
