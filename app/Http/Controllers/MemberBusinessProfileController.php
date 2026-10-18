<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MemberBusinessProfileController extends Controller
{
    public function update(Request $request, Business $business): JsonResponse
    {
        $business = $this->manageableBusiness($request, $business);
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'additional_categories' => ['sometimes', 'array', 'max:9'],
            'additional_categories.*' => ['string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'year_established' => ['nullable', 'integer', 'min:1700', 'max:'.(now()->year + 1)],
            'employee_count' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'facebook_url' => ['nullable', 'url:https', 'max:2048'],
            'instagram_url' => ['nullable', 'url:https', 'max:2048'],
            'linkedin_url' => ['nullable', 'url:https', 'max:2048'],
            'tiktok_url' => ['nullable', 'url:https', 'max:2048'],
            'youtube_url' => ['nullable', 'url:https', 'max:2048'],
            'other_social_url' => ['nullable', 'url:https', 'max:2048'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'size:2'],
            'specialties' => ['sometimes', 'array', 'max:30'],
            'specialties.*' => ['string', 'max:100'],
            'accessibility_information' => ['nullable', 'string', 'max:2000'],
            'appointment_methods' => ['sometimes', 'array', 'max:20'],
            'appointment_methods.*' => ['string', 'max:100'],
            'service_areas' => ['sometimes', 'array', 'max:30'],
            'service_areas.*' => ['string', 'max:150'],
        ]);

        DB::transaction(function () use ($business, $data, $request): void {
            $old = [
                'company_name' => $business->company_name,
                'contact_email' => $business->contact_email,
                'is_published' => $business->is_published,
            ];
            $attributes = collect($data)->except(['category', 'additional_categories'])->all();
            if ($business->company_name !== $data['company_name']) {
                $base = Str::slug($data['company_name']) ?: 'business';
                $slug = $base;
                $suffix = 2;
                while (Business::query()->where('slug', $slug)->whereKeyNot($business->id)->exists()) {
                    $slug = $base.'-'.$suffix++;
                }
                $attributes['slug'] = $slug;
            }
            $business->fill($attributes);
            $business->settings = array_merge($business->settings ?? [], ['category' => $data['category']]);
            $business->save();

            $categories = collect([$data['category'], ...($data['additional_categories'] ?? [])])
                ->map(fn (string $name): string => trim($name))
                ->filter()
                ->unique(fn (string $name): string => mb_strtolower($name));
            $sync = [];
            foreach ($categories as $index => $name) {
                $category = BusinessCategory::query()->firstOrCreate(
                    ['name' => $name],
                    ['slug' => $this->uniqueCategorySlug(Str::slug($name) ?: 'category')]
                );
                $sync[$category->id] = ['is_primary' => $index === 0];
            }
            $business->categories()->sync($sync);

            AuditLog::record($request->user(), 'business.member_profile_updated', $business, $old, [
                'company_name' => $business->company_name,
                'contact_email' => $business->contact_email,
                'is_published' => $business->is_published,
            ]);
        });

        return response()->json(['message' => 'Business profile saved.']);
    }

    public function publish(Request $request, Business $business): JsonResponse
    {
        $business = $this->manageableBusiness($request, $business);
        $data = $request->validate(['is_published' => ['required', 'boolean']]);

        if ($data['is_published']) {
            $business->load('categories');
            $categoryName = $business->categories->firstWhere('pivot.is_primary', true)?->name
                ?? $business->categories->first()?->name
                ?? data_get($business->settings, 'category');
            if (blank($business->company_name) || blank($categoryName)) {
                throw ValidationException::withMessages([
                    'business' => 'Add a business name and category before publishing.',
                ]);
            }
            if (! $business->is_active) {
                throw ValidationException::withMessages([
                    'business' => 'This business profile is inactive. Contact support to reactivate it before publishing.',
                ]);
            }
            if ($business->images()->count() < 2) {
                throw ValidationException::withMessages([
                    'images' => 'Add at least two business photos before publishing your directory profile.',
                ]);
            }
            if ($business->categories->isEmpty()) {
                $category = BusinessCategory::query()->firstOrCreate(
                    ['name' => $categoryName],
                    ['slug' => $this->uniqueCategorySlug(Str::slug($categoryName) ?: 'category')]
                );
                $business->categories()->attach($category->id, ['is_primary' => true]);
            }
        }

        $old = [
            'is_published' => $business->is_published,
            'directory_visibility' => $business->directory_visibility,
        ];
        $published = (bool) $data['is_published'];
        DB::transaction(function () use ($business, $published, $old, $request): void {
            $business->forceFill([
                'is_published' => $published,
                'directory_visibility' => $published ? 'public' : 'private',
                'published_at' => $published ? ($business->published_at ?? now()) : null,
            ])->save();
            AuditLog::record(
                $request->user(),
                $published ? 'business.member_published' : 'business.member_unpublished',
                $business,
                $old,
                ['is_published' => $published, 'directory_visibility' => $business->directory_visibility],
            );
        });

        return response()->json([
            'is_published' => $business->is_published,
            'directory_visibility' => $business->directory_visibility,
        ]);
    }

    public function uploadImages(Request $request, Business $business): JsonResponse
    {
        $business = $this->manageableBusiness($request, $business);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'alt_text' => ['sometimes', 'array', 'max:10'],
            'alt_text.*' => ['nullable', 'string', 'max:255'],
        ]);

        $storedPaths = [];
        try {
            $images = DB::transaction(function () use ($business, $data, &$storedPaths): array {
                $locked = Business::query()->lockForUpdate()->findOrFail($business->id);
                $existingCount = $locked->images()->count();
                if ($existingCount + count($data['images']) > 10) {
                    throw ValidationException::withMessages([
                        'images' => 'A business profile may have no more than 10 photos.',
                    ]);
                }
                $created = [];
                foreach ($data['images'] as $index => $file) {
                    $path = $file->store('businesses/'.$locked->id.'/images', 'local');
                    $storedPaths[] = $path;
                    $created[] = $locked->images()->create([
                        'file_path' => $path,
                        'alt_text' => $data['alt_text'][$index] ?? null,
                        'sort_order' => $existingCount + $index,
                        'is_primary' => $existingCount === 0 && $index === 0,
                    ]);
                }

                return $created;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        foreach ($images as $image) {
            AuditLog::record($request->user(), 'business.member_image_uploaded', $image, [], [
                'business_id' => $business->id,
                'alt_text' => $image->alt_text,
            ]);
        }

        return response()->json([
            'images' => $business->images()->get()->map(fn (BusinessImage $image): array => [
                'id' => $image->id,
                'url' => route('business-media.images', $image),
                'alt_text' => $image->alt_text,
                'is_primary' => $image->is_primary,
            ]),
            'image_count' => $business->images()->count(),
        ], 201);
    }

    private function manageableBusiness(Request $request, Business $business): Business
    {
        abort_if($business->deleted_at !== null, 404);

        $association = $request->user()->businessAssociations()
            ->where('business_id', $business->id)
            ->where('status', 'active')
            ->first();
        abort_unless($association, 404);

        abort_unless(
            in_array($association->role, ['owner', 'admin'], true)
            || in_array($association->access_level, ['owner', 'admin'], true),
            403
        );

        return $business;
    }

    private function uniqueCategorySlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        while (BusinessCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
