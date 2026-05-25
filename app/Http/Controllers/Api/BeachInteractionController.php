<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beach;
use App\Models\FavoriteBeach;
use App\Services\BeachInteractionService;
use App\Services\VisitorResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class BeachInteractionController extends Controller
{
    public function reaction(
        Request $request,
        VisitorResolver $visitorResolver,
        BeachInteractionService $interactionService,
        Beach $beach
    ) {
        $validated = $request->validate([
            'reaction_type' => ['required', 'in:positive,negative'],
        ]);

        $visitor = $visitorResolver->currentOrCreate($request);
        $result = $interactionService->addReaction($visitor, (int) $beach->id, $validated['reaction_type']);

        return response()->json([
            ...$result,
            'reaction_stats' => $interactionService->reactionStats((int) $beach->id),
            ...$interactionService->reactionAvailability($visitor, (int) $beach->id),
        ], $result['success'] ? 200 : 429);
    }

    public function favorites(Request $request, VisitorResolver $visitorResolver)
    {
        $visitor = $visitorResolver->current($request);

        if (!$visitor) {
            return response()->json(['favorites' => []]);
        }

        $favorites = FavoriteBeach::query()
            ->with('beach')
            ->where('visitor_id', $visitor->id)
            ->latest('created_at')
            ->get()
            ->map(fn (FavoriteBeach $favorite) => [
                'id' => $favorite->beach->id,
                'name' => $favorite->beach->name,
                'number' => $favorite->beach->number,
                'wave_level' => $favorite->beach->wave_level,
                'effective_wave_level' => $favorite->beach->effective_wave_level,
                'category_key' => $favorite->beach->category_key,
                'category_label' => $favorite->beach->category_label,
            ])
            ->values();

        return response()->json(['favorites' => $favorites]);
    }

    public function favoriteToggle(Request $request, VisitorResolver $visitorResolver, Beach $beach)
    {
        $visitor = $visitorResolver->currentOrCreate($request);

        $favorite = FavoriteBeach::query()
            ->where('visitor_id', $visitor->id)
            ->where('beach_id', $beach->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'is_favorite' => false,
                'message' => 'Пляж удалён из избранного.',
            ]);
        }

        try {
            FavoriteBeach::query()->firstOrCreate([
                'visitor_id' => $visitor->id,
                'beach_id' => $beach->id,
            ]);
        } catch (QueryException) {
            // Unique constraint handles concurrent duplicate requests.
        }

        return response()->json([
            'is_favorite' => true,
            'message' => 'Пляж добавлен в избранное.',
        ]);
    }
}
