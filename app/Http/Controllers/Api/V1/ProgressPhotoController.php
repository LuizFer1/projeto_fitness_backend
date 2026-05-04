<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProgressPhoto;
use App\Services\AuditLogger;
use App\Services\GamificationService;
use App\Services\Storage\EncryptedPhotoStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressPhotoController extends Controller
{
    public function __construct(
        private EncryptedPhotoStorageService $storage,
        private GamificationService $gamification,
        private AuditLogger $audit,
    ) {}

    /**
     * POST /v1/progress-photos
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo'     => 'required|file|mimes:jpeg,jpg,png,heic|max:20480',
            'taken_at'  => 'nullable|date',
            'weight_kg' => 'nullable|numeric|min:1|max:500',
            'category'  => 'nullable|in:front,side,back,other',
            'caption'   => 'nullable|string|max:500',
            'notes'     => 'nullable|string|max:1000',
        ]);

        $user  = $request->user();
        $photo = $this->storage->store($user, $request->file('photo'), $validated);

        $this->gamification->grantProgressPhotoXp($user);

        return response()->json([
            'message' => 'Foto registrada com sucesso.',
            'photo'   => $this->formatPhoto($photo, null),
        ], 201);
    }

    /**
     * GET /v1/progress-photos
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = ProgressPhoto::where('user_id', $user->id)->orderByDesc('taken_at');

        if ($request->filled('from')) {
            $query->where('taken_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('taken_at', '<=', $request->to);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $photos = $query->paginate(20);

        // Generate presigned URLs for the current page only
        $items = $photos->getCollection()->map(
            fn ($p) => $this->formatPhoto($p, $this->storage->presignedUrl($p))
        );

        return response()->json(array_merge($photos->toArray(), ['data' => $items]));
    }

    /**
     * GET /v1/progress-photos/{uuid}
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user  = $request->user();
        $photo = ProgressPhoto::where('user_id', $user->id)->findOrFail($uuid);

        $this->audit->log('progress_photo_viewed', $user->id, $photo->id, 'ProgressPhoto');

        return response()->json([
            'photo' => $this->formatPhoto($photo, $this->storage->presignedUrl($photo)),
        ]);
    }

    /**
     * DELETE /v1/progress-photos/{uuid}
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user  = $request->user();
        $photo = ProgressPhoto::where('user_id', $user->id)->findOrFail($uuid);

        $this->storage->softDelete($photo);

        return response()->json(['message' => 'Foto removida.']);
    }

    private function formatPhoto(ProgressPhoto $photo, ?string $url): array
    {
        return [
            'id'        => $photo->id,
            'taken_at'  => $photo->taken_at,
            'weight_kg' => $photo->weight_kg,
            'category'  => $photo->category,
            'caption'   => $photo->caption,
            'notes'     => $photo->notes,
            'url'       => $url,
        ];
    }
}
