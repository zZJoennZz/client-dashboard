<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:name,featured,newest'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $queryText = trim($filters['q'] ?? '');
        $query = Business::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('directory_visibility', 'public')
            ->with(['categories', 'images', 'video']);

        if ($queryText !== '') {
            $query->where(function (Builder $businesses) use ($queryText): void {
                $like = '%'.$queryText.'%';
                $businesses
                    ->where('company_name', 'like', $like)
                    ->orWhere('tagline', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('state', 'like', $like)
                    ->orWhereHas('categories', fn (Builder $categories) => $categories->where('name', 'like', $like));
            });
        }
        if (! empty($filters['category'])) {
            $query->whereHas('categories', fn (Builder $categories) => $categories->where('slug', $filters['category']));
        }
        if (! empty($filters['location'])) {
            $location = '%'.$filters['location'].'%';
            $query->where(fn (Builder $businesses) => $businesses
                ->where('city', 'like', $location)
                ->orWhere('state', 'like', $location)
                ->orWhere('postal_code', 'like', $location));
        }

        match ($filters['sort'] ?? 'featured') {
            'name' => $query->orderBy('company_name'),
            'newest' => $query->orderByDesc('published_at')->orderBy('company_name'),
            default => $query->orderByDesc('is_featured')->orderBy('company_name'),
        };
        $businesses = $query->paginate(12)->withQueryString();
        $categories = BusinessCategory::query()
            ->whereHas('businesses', fn (Builder $listings) => $listings
                ->where('is_active', true)
                ->where('is_published', true)
                ->where('directory_visibility', 'public'))
            ->orderBy('name')
            ->get(['name', 'slug']);
        $locations = Business::query()
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('directory_visibility', 'public')
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city');

        return view('businesses.index', [
            'businesses' => $businesses,
            'categories' => $categories,
            'locations' => $locations,
            'filters' => [
                'query' => $queryText,
                'category' => $filters['category'] ?? '',
                'location' => $filters['location'] ?? '',
                'sort' => $filters['sort'] ?? 'featured',
            ],
        ]);
    }

    public function show(string $slug): View
    {
        $business = Business::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('directory_visibility', 'public')
            ->with(['categories', 'images', 'hours', 'highlights' => fn ($query) => $query->where('is_active', true), 'video'])
            ->firstOrFail();

        return $this->profileView($business);
    }

    public function preview(Request $request, Business $business): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 404);
        $business->load(['categories', 'images', 'hours', 'highlights', 'video']);

        return $this->profileView($business);
    }

    private function profileView(Business $business): View
    {
        $address = collect([
            $business->address_line_1,
            $business->address_line_2,
            $business->city,
            $business->state,
            $business->postal_code,
            $business->country,
        ])->filter(fn (?string $part): bool => filled($part))->implode(', ');
        $business->setAttribute('formatted_address', $address);
        $business->setAttribute(
            'google_maps_url',
            $address !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address) : null
        );
        $business->setAttribute('seo_title', $business->meta_title ?: $business->company_name.' · The Argyle Buzz');
        $business->setAttribute('seo_description', $business->meta_description
            ?: Str::limit(strip_tags($business->short_description ?: $business->description ?: $business->tagline ?: ''), 155));
        $business->setAttribute('canonical_url', URL::route('businesses.show', $business->slug));
        if ($business->video?->source_type === 'youtube') {
            $business->setAttribute('video_embed_url', 'https://www.youtube-nocookie.com/embed/'.$this->youtubeVideoId($business->video->source_url));
            $business->setAttribute('video_playback_url', null);
        } elseif ($business->video?->source_type === 'external') {
            $business->setAttribute('video_embed_url', null);
            $business->setAttribute('video_playback_url', $business->video->source_url);
        } elseif ($business->video?->source_type === 'upload') {
            $business->setAttribute('video_embed_url', null);
            $business->setAttribute('video_playback_url', route('business-media.videos', $business->video));
        }

        return view('businesses.show', compact('business'));
    }

    private function youtubeVideoId(?string $url): string
    {
        $parts = parse_url((string) $url);
        $host = strtolower($parts['host'] ?? '');
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            return trim($parts['path'] ?? '', '/');
        }
        if (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            if (is_string($query['v'] ?? null)) {
                return $query['v'];
            }
            if (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})$~', $parts['path'] ?? '', $matches)) {
                return $matches[1];
            }
        }

        abort(404);
    }
}
