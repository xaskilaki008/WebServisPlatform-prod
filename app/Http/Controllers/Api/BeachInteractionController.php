<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Beach;
use App\Models\FavoriteBeach;
use App\Models\User;
use App\Services\BeachInteractionService;
use Illuminate\Http\Request;

class BeachInteractionController extends Controller
{
    public function reaction(
        Request $request,
        BeachInteractionService $interactionService,
        Beach $beach
    ) {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'auth_required' => true,
                'message' => 'Войдите в систему, чтобы оставить реакцию',
                'reaction_stats' => $interactionService->reactionStats((int) $beach->id),
                ...$interactionService->reactionAvailability(null, (int) $beach->id),
            ], 401);
        }

        $validated = $request->validate([
            'reaction_type' => ['required', 'in:positive,negative'],
        ]);

        $result = $interactionService->addReaction($user, (int) $beach->id, $validated['reaction_type']);

        return response()->json([
            ...$result,
            'reaction_stats' => $interactionService->reactionStats((int) $beach->id),
            ...$interactionService->reactionAvailability($user, (int) $beach->id),
        ], $result['success'] ? 200 : 429);
    }

    public function favorites(Request $request)
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json(['favorites' => []]);
        }

        $favorites = FavoriteBeach::query()
            ->with('beach')
            ->where('user_id', $user->id)
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

    public function favoriteToggle(Request $request, BeachInteractionService $interactionService, Beach $beach)
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'auth_required' => true,
                'is_favorite' => false,
                'message' => 'Войдите в систему, чтобы добавить пляж в избранное',
            ], 401);
        }

        return response()->json($interactionService->toggleFavorite($user, $beach));
    }
}
