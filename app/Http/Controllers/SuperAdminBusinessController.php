<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessHour;
use App\Models\BusinessHighlight;
use App\Models\BusinessImage;
use App\Models\BusinessVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Http\UploadedFile;

class SuperAdminBusinessController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        $business = DB::transaction(function () use ($data, $request): Business {
            $attributes = Arr::except($data, ['category', 'additional_categories', 'hours', 'highlights']);
            $business = Business::query()->create([
                ...$attributes,
                'slug' => $this->uniqueSlug($data['company_name']),
                'status' => 'active',
                'is_active' => $data['is_active'] ?? true,
                'is_published' => false,
                'directory_visibility' => $data['directory_visibility'] ?? 'private',
                'settings' => ['category' => $data['category']],
            ]);
            $this->syncCategories($business, $data);
            $this->syncHours($business, $data['hours'] ?? []);
            $this->syncHighlights($business, $data['highlights'] ?? []);
            AuditLog::record($request->user(), 'business.created', $business, [], $this->auditData($business));

            return $business;
        });

        return response()->json(['id' => $business->id, 'name' => $business->company_name], 201);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        abort_if($business->deleted_at !== null, 404);
        $data = $this->validatedData($request, $business);

        DB::transaction(function () use ($business, $data, $request): void {
            $old = $this->auditData($business);
            $attributes = Arr::except($data, ['category', 'additional_categories', 'hours', 'highlights']);
            if ($business->company_name !== $data['company_name']) {
                $attributes['slug'] = $this->uniqueSlug($data['company_name'], $business->id);
            }
            $business->fill($attributes);
            $business->settings = array_merge($business->settings ?? [], ['category' => $data['category']]);
            $business->save();
            $this->syncCategories($business, $data);
            $this->syncHours($business, $data['hours'] ?? []);
            $this->syncHighlights($business, $data['highlights'] ?? []);
            AuditLog::record($request->user(), 'business.updated', $business, $old, $this->auditData($business));
        });

        return response()->json(['id' => $business->id, 'name' => $business->company_name]);
    }

    public function uploadImages(Request $request, Business $business): JsonResponse
    {
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
                    throw ValidationException::withMessages(['images' => 'A business listing may have no more than 10 images.']);
                }

                $created = [];
                foreach ($data['images'] as $index => $file) {
                    /** @var UploadedFile $file */
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
            AuditLog::record($request->user(), 'business.image_uploaded', $image, [], $this->imageData($image));
        }

        return response()->json([
            'data' => collect($images)->map(fn (BusinessImage $image): array => $this->imageData($image)),
            'count' => $business->images()->count(),
        ], 201);
    }

    public function updateImage(Request $request, Business $business, BusinessImage $image): JsonResponse
    {
        abort_unless($image->business_id === $business->id, 404);
        $data = $request->validate([
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:9'],
            'is_primary' => ['sometimes', 'required', 'boolean'],
        ]);

        DB::transaction(function () use ($business, $image, $data): void {
            if (($data['is_primary'] ?? false) === true) {
                $business->images()->whereKeyNot($image->id)->update(['is_primary' => false]);
            }
            $image->fill($data)->save();
        });
        AuditLog::record($request->user(), 'business.image_updated', $image, [], $this->imageData($image->fresh()));

        return response()->json($this->imageData($image->fresh()));
    }

    public function deleteImage(Request $request, Business $business, BusinessImage $image): JsonResponse
    {
        abort_unless($image->business_id === $business->id, 404);
        $count = $business->images()->count();
        if ($business->is_published && $count <= 2) {
            throw ValidationException::withMessages(['image' => 'A published listing must keep at least two images. Unpublish it before reducing its gallery.']);
        }
        $wasPrimary = $image->is_primary;
        $path = $image->file_path;
        DB::transaction(function () use ($image, $business, $wasPrimary): void {
            $image->delete();
            if ($wasPrimary) {
                $business->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_primary' => true]);
            }
        });
        AuditLog::record($request->user(), 'business.image_removed', $business, ['image_id' => $image->id], []);
        if (! BusinessImage::query()->where('file_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }

        return response()->json(['count' => $business->images()->count()]);
    }

    public function saveVideo(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'source_type' => ['required', Rule::in(['youtube', 'external', 'upload'])],
            'youtube_url' => ['required_if:source_type,youtube', 'nullable', 'url:https', 'max:2048'],
            'external_url' => ['required_if:source_type,external', 'nullable', 'url:https', 'max:2048'],
            'video_file' => ['required_if:source_type,upload', 'nullable', 'file', 'mimes:mp4,webm', 'max:10240'],
        ]);

        $oldVideo = $business->video;
        $attributes = ['source_type' => $data['source_type'], 'source_url' => null, 'file_path' => null];
        if ($data['source_type'] === 'youtube') {
            $attributes['source_url'] = $this->youtubeWatchUrl($data['youtube_url']);
        } elseif ($data['source_type'] === 'external') {
            $external = $data['external_url'];
            if (! preg_match('/\.(mp4|webm|ogg)(?:$|[?#])/i', $external)) {
                throw ValidationException::withMessages(['external_url' => 'Use an HTTPS link to a supported MP4, WebM, or Ogg video file.']);
            }
            $attributes['source_url'] = $external;
        }

        $newPath = null;
        if ($data['source_type'] === 'upload') {
            $newPath = $request->file('video_file')->store('businesses/'.$business->id.'/videos', 'local');
            $attributes['file_path'] = $newPath;
        }

        try {
            $video = DB::transaction(fn () => BusinessVideo::query()->updateOrCreate(
                ['business_id' => $business->id],
                $attributes
            ));
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }
        if ($oldVideo?->file_path && $oldVideo->file_path !== $video->file_path) {
            Storage::disk('local')->delete($oldVideo->file_path);
        }
        AuditLog::record($request->user(), 'business.video_updated', $video, [], [
            'source_type' => $video->source_type,
            'source_url' => $video->source_url,
            'file_path' => $video->file_path ? '[private upload]' : null,
        ]);

        return response()->json([
            'source_type' => $video->source_type,
            'source_url' => $video->source_url,
            'embed_url' => $video->source_type === 'youtube'
                ? 'https://www.youtube-nocookie.com/embed/'.$this->youtubeVideoId($video->source_url)
                : null,
            'video_url' => $video->source_type === 'upload' ? route('business-media.videos', $video) : null,
        ]);
    }

    public function deleteVideo(Request $request, Business $business): JsonResponse
    {
        $video = $business->video;
        if (! $video) {
            return response()->noContent();
        }
        $path = $video->file_path;
        DB::transaction(fn () => $video->delete());
        AuditLog::record($request->user(), 'business.video_removed', $business, [
            'source_type' => $video->source_type,
        ], []);
        if ($path && ! BusinessVideo::query()->where('file_path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }

        return response()->noContent();
    }

    private function validatedData(Request $request, ?Business $business = null): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'additional_categories' => ['sometimes', 'array', 'max:9'],
            'additional_categories.*' => ['string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'year_established' => ['nullable', 'integer', 'min:1700', 'max:'.(now()->year + 1)],
            'employee_count' => ['nullable', 'string', 'max:60'],
            'ownership_information' => ['nullable', 'string', 'max:2000'],
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
            'country' => ['required', 'string', 'size:2'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'specialties' => ['sometimes', 'array', 'max:30'],
            'specialties.*' => ['string', 'max:100'],
            'accessibility_information' => ['nullable', 'string', 'max:2000'],
            'appointment_methods' => ['sometimes', 'array', 'max:20'],
            'appointment_methods.*' => ['string', 'max:100'],
            'service_areas' => ['sometimes', 'array', 'max:30'],
            'service_areas.*' => ['string', 'max:150'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'hours' => ['sometimes', 'array', 'max:7'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'hours.*.is_closed' => ['required', 'boolean'],
            'hours.*.by_appointment' => ['required', 'boolean'],
            'highlights' => ['sometimes', 'array', 'max:20'],
            'highlights.*.name' => ['required', 'string', 'max:255'],
            'highlights.*.description' => ['nullable', 'string', 'max:2000'],
            'highlights.*.price_information' => ['nullable', 'string', 'max:120'],
            'highlights.*.is_active' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'directory_visibility' => ['sometimes', Rule::in(['public', 'private', 'members'])],
        ]);
    }

    private function uniqueSlug(string $companyName, ?int $exceptId = null): string
    {
        $base = Str::slug($companyName) ?: 'business';
        $slug = $base;
        $suffix = 2;

        while (Business::query()->where('slug', $slug)->when($exceptId, fn ($query) => $query->where('id', '<>', $exceptId))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function syncCategories(Business $business, array $data): void
    {
        $names = collect([$data['category'], ...($data['additional_categories'] ?? [])])
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values();
        $sync = [];
        foreach ($names as $index => $name) {
            $baseSlug = Str::slug($name) ?: 'category';
            $category = BusinessCategory::query()->firstOrCreate(
                ['name' => $name],
                ['slug' => $this->uniqueCategorySlug($baseSlug)]
            );
            $sync[$category->id] = ['is_primary' => $index === 0];
        }
        $business->categories()->sync($sync);
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

    private function syncHours(Business $business, array $hours): void
    {
        foreach ($hours as $hour) {
            $hasOpening = filled($hour['opens_at'] ?? null);
            $hasClosing = filled($hour['closes_at'] ?? null);
            if (! ($hour['is_closed'] ?? false) && ! ($hour['by_appointment'] ?? false) && $hasOpening !== $hasClosing) {
                throw ValidationException::withMessages(['hours' => 'Provide both opening and closing times, or mark the day closed or by appointment.']);
            }
            if (! ($hour['is_closed'] ?? false) && ! ($hour['by_appointment'] ?? false) && ! $hasOpening) {
                throw ValidationException::withMessages(['hours' => 'Each listed open day needs opening and closing times.']);
            }
        }
        $rows = collect($hours)->keyBy('day_of_week')->map(fn (array $hour): array => [
            'opens_at' => $hour['opens_at'] ?: null,
            'closes_at' => $hour['closes_at'] ?: null,
            'is_closed' => (bool) $hour['is_closed'],
            'by_appointment' => (bool) $hour['by_appointment'],
        ])->all();
        foreach ($rows as $day => $values) {
            BusinessHour::query()->updateOrCreate(['business_id' => $business->id, 'day_of_week' => $day], $values);
        }
        $business->hours()->whereNotIn('day_of_week', array_keys($rows) ?: [-1])->delete();
    }

    private function syncHighlights(Business $business, array $highlights): void
    {
        $business->highlights()->delete();
        foreach (array_values($highlights) as $order => $highlight) {
            $business->highlights()->create([
                ...$highlight,
                'sort_order' => $order,
                'is_active' => (bool) ($highlight['is_active'] ?? true),
            ]);
        }
    }

    private function auditData(Business $business): array
    {
        return [
            'company_name' => $business->company_name,
            'category' => data_get($business->settings, 'category'),
            'contact_email' => $business->contact_email,
            'is_active' => $business->is_active,
            'is_published' => $business->is_published,
            'directory_visibility' => $business->directory_visibility,
        ];
    }

    private function imageData(BusinessImage $image): array
    {
        return [
            'id' => $image->id,
            'url' => route('business-media.images', $image),
            'alt_text' => $image->alt_text,
            'sort_order' => $image->sort_order,
            'is_primary' => $image->is_primary,
        ];
    }

    private function youtubeWatchUrl(string $url): string
    {
        return 'https://www.youtube.com/watch?v='.$this->youtubeVideoId($url);
    }

    private function youtubeVideoId(string $url): string
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $videoId = null;
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $videoId = trim($parts['path'] ?? '', '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $videoId = $query['v'] ?? null;
            if (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})$~', $parts['path'] ?? '', $matches)) {
                $videoId = $matches[1];
            }
        }
        if (! is_string($videoId) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId)) {
            throw ValidationException::withMessages(['youtube_url' => 'Enter a valid YouTube watch or share URL.']);
        }

        return $videoId;
    }
}
