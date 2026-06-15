<?php

namespace App\Services;

use App\Models\Beach;
use App\Models\FavoriteBeach;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\QueryException;

class BeachInteractionService
{
    private const REACTION_WINDOW_MINUTES = 10;

    public function reactionStats(int $beachId): array
    {
        $since = now()->subHour();

        $positive = Reaction::query()
            ->where('beach_id', $beachId)
            ->where('created_at', '>=', $since)
            ->where('reaction_type', 'positive')
            ->count();

        $negative = Reaction::query()
            ->where('beach_id', $beachId)
            ->where('created_at', '>=', $since)
            ->where('reaction_type', 'negative')
            ->count();

        $total = $positive + $negative;

        return [
            'positive' => $positive,
            'negative' => $negative,
            'total' => $total,
            'positive_percent' => $total > 0 ? (int) round(($positive / $total) * 100) : 0,
        ];
    }

    public function reactionAvailability(?User $user, int $beachId): array
    {
        if (!$user) {
            return [
                'can_react' => false,
                'auth_required' => true,
                'message' => 'Войдите в систему, чтобы оставить реакцию',
                'reaction_retry_after_seconds' => 0,
            ];
        }

        $latestReaction = Reaction::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        if (!$latestReaction) {
            return [
                'can_react' => true,
                'auth_required' => false,
                'reaction_retry_after_seconds' => 0,
            ];
        }

        $availableAt = $latestReaction->created_at->copy()->addMinutes(self::REACTION_WINDOW_MINUTES);

        if ($availableAt->lte(now())) {
            return [
                'can_react' => true,
                'auth_required' => false,
                'reaction_retry_after_seconds' => 0,
            ];
        }

        return [
            'can_react' => false,
            'auth_required' => false,
            'reaction_retry_after_seconds' => max(1, now()->diffInSeconds($availableAt)),
        ];
    }

    public function isFavorite(?User $user, int $beachId): bool
    {
        if (!$user) {
            return false;
        }

        return FavoriteBeach::query()
            ->where('user_id', $user->id)
            ->where('beach_id', $beachId)
            ->exists();
    }

    public function addReaction(User $user, int $beachId, string $reactionType): array
    {
        $availability = $this->reactionAvailability($user, $beachId);

        if (!$availability['can_react']) {
            return [
                'success' => false,
                'message' => 'Вы уже поставили реакцию. Следующую можно отправить позже.',
                ...$availability,
            ];
        }

        Reaction::query()->create([
            'beach_id' => $beachId,
            'user_id' => $user->id,
            'reaction_type' => $reactionType,
        ]);

        return [
            'success' => true,
            'message' => 'Реакция сохранена.',
            ...$this->reactionAvailability($user, $beachId),
        ];
    }

    public function toggleFavorite(User $user, Beach $beach): array
    {
        $favorite = FavoriteBeach::query()
            ->where('user_id', $user->id)
            ->where('beach_id', $beach->id)
            ->first();

        if ($favorite) {
            $favorite->delete();

            return [
                'success' => true,
                'is_favorite' => false,
                'message' => 'Пляж удалён из избранного.',
            ];
        }

        try {
            FavoriteBeach::query()->firstOrCreate([
                'user_id' => $user->id,
                'beach_id' => $beach->id,
            ]);
        } catch (QueryException) {
            // Unique constraint handles concurrent duplicate requests.
        }

        return [
            'success' => true,
            'is_favorite' => true,
            'message' => 'Пляж добавлен в избранное.',
        ];
    }
}
