<?php

namespace App\Http\Controllers;

use App\Services\CardLayoutService;
use App\Models\CardLayout;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CardLayoutController extends Controller
{
    private CardLayoutService $layoutService;

    public function __construct(CardLayoutService $layoutService)
    {
        $this->layoutService = $layoutService;
    }

    /**
     * List layouts for event or global layouts
     */
    public function index(Request $request): JsonResponse
    {
        $eventId = $request->query('event_id');

        $query = CardLayout::query();

        if ($eventId) {
            $query->where(function($q) use ($eventId) {
                $q->where('event_id', $eventId)
                  ->orWhere('is_default', true);
            });
        } else {
            $query->where('is_default', true);
        }

        $layouts = $query->where('is_active', true)
            ->with('creator')
            ->get();

        return response()->json([
            'data' => $layouts->map(fn($layout) => [
                'id' => $layout->id,
                'name' => $layout->name,
                'description' => $layout->description,
                'background_url' => $layout->getBackgroundUrl(),
                'config' => $layout->getMergedConfig(),
                'is_default' => $layout->is_default,
                'created_by' => $layout->creator->name,
                'created_at' => $layout->created_at,
            ]),
        ]);
    }

    /**
     * Create new layout
     */
    public function store(Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'event_id' => 'nullable|uuid|exists:events,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'config' => 'nullable|json',
            'is_default' => 'nullable|boolean',
            'background' => 'nullable|image|max:5120', // 5MB
        ]);

        try {
            // Parse config if string
            if (isset($validated['config']) && is_string($validated['config'])) {
                $validated['config'] = json_decode($validated['config'], true);
            }

            $layout = $this->layoutService->createLayout($validated, $request->user()->id);

            // Upload background if provided
            if ($request->hasFile('background')) {
                $this->layoutService->uploadBackground($layout, $request->file('background'));
            }

            // Set as default if requested
            if ($validated['is_default'] ?? false) {
                $this->layoutService->setAsDefault($layout);
            }

            return response()->json([
                'message' => 'Layout created successfully',
                'layout' => [
                    'id' => $layout->id,
                    'name' => $layout->name,
                    'description' => $layout->description,
                    'background_url' => $layout->getBackgroundUrl(),
                    'config' => $layout->getMergedConfig(),
                    'is_default' => $layout->is_default,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create layout',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get layout details
     */
    public function show(CardLayout $layout): JsonResponse
    {
        return response()->json([
            'layout' => [
                'id' => $layout->id,
                'name' => $layout->name,
                'description' => $layout->description,
                'background_url' => $layout->getBackgroundUrl(),
                'config' => $layout->getMergedConfig(),
                'is_default' => $layout->is_default,
                'is_active' => $layout->is_active,
                'created_by' => $layout->creator->name,
                'created_at' => $layout->created_at,
                'updated_at' => $layout->updated_at,
            ],
        ]);
    }

    /**
     * Update layout configuration
     */
    public function updateConfig(CardLayout $layout, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $layout->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'config' => 'required|json',
        ]);

        try {
            $config = is_string($validated['config'])
                ? json_decode($validated['config'], true)
                : $validated['config'];

            $layout = $this->layoutService->updateConfig($layout, $config);

            return response()->json([
                'message' => 'Layout configuration updated',
                'layout' => [
                    'id' => $layout->id,
                    'config' => $layout->getMergedConfig(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update layout',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Upload new background
     */
    public function uploadBackground(CardLayout $layout, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $layout->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'background' => 'required|image|max:5120', // 5MB
        ]);

        try {
            $path = $this->layoutService->uploadBackground($layout, $request->file('background'));

            return response()->json([
                'message' => 'Background uploaded successfully',
                'layout' => [
                    'id' => $layout->id,
                    'background_url' => $layout->getBackgroundUrl(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload background',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Set as default layout for event
     */
    public function setDefault(CardLayout $layout, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->layoutService->setAsDefault($layout);

            return response()->json([
                'message' => 'Layout set as default',
                'layout' => ['id' => $layout->id, 'is_default' => true],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to set as default',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete layout
     */
    public function destroy(CardLayout $layout, Request $request): JsonResponse
    {
        // Authorization
        if (!$request->user()->canManageEvents() || $layout->created_by !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->layoutService->delete($layout);

            return response()->json([
                'message' => 'Layout deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete layout',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
