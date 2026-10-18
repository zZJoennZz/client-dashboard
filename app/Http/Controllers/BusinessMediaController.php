<?php

namespace App\Http\Controllers;

use App\Models\BusinessImage;
use App\Models\BusinessVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BusinessMediaController extends Controller
{
    public function image(Request $request, BusinessImage $image): BinaryFileResponse
    {
        $business = $image->business;
        abort_unless($request->user()?->isSuperAdmin() || $this->isPublic($business), 404);
        abort_unless(Storage::disk('local')->exists($image->file_path), 404);

        return response()->file(Storage::disk('local')->path($image->file_path), [
            'Content-Type' => Storage::disk('local')->mimeType($image->file_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function video(Request $request, BusinessVideo $video): BinaryFileResponse
    {
        $business = $video->business;
        abort_unless($request->user()?->isSuperAdmin() || $this->isPublic($business), 404);
        abort_unless($video->source_type === 'upload' && $video->file_path, 404);
        abort_unless(Storage::disk('local')->exists($video->file_path), 404);

        return response()->file(Storage::disk('local')->path($video->file_path), [
            'Content-Type' => Storage::disk('local')->mimeType($video->file_path) ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isPublic($business): bool
    {
        if (! $business) {
            return false;
        }

        return request()->user()?->businesses()
            ->whereKey($business->id)
            ->wherePivot('status', 'active')
            ->exists()
            || ($business
            && $business->is_active
            && $business->is_published
            && $business->directory_visibility === 'public');
    }
}
