<?php

namespace App\Services;

use App\Models\FavoriteBeach;
use App\Models\Reaction;
use App\Models\Visitor;

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

    public function reactionAvailability(?Visitor $visitor, int $beachId): array
    {
        if (!$visitor) {
            return [
                'can_react' => true,
                'reaction_retry_after_seconds' => 0,
            ];
        }

        $latestReaction = Reaction::query()
            ->where('visitor_id', $visitor->id)
            ->latest('created_at')
            ->first();

        if (!$latestReaction) {
            return [
                'can_react' => true,
                'reaction_retry_after_seconds' => 0,
            ];
        }

        $availableAt = $latestReaction->created_at->copy()->addMinutes(self::REACTION_WINDOW_MINUTES);

        if ($availableAt->lte(now())) {
            return [
                'can_react' => true,
                'reaction_retry_after_seconds' => 0,
            ];
        }

        return [
            'can_react' => false,
            'reaction_retry_after_seconds' => max(1, now()->diffInSeconds($availableAt)),
        ];
    }

    public function isFavorite(?Visitor $visitor, int $beachId): bool
    {
        if (!$visitor) {
            return false;
        }

        return FavoriteBeach::query()
            ->where('visitor_id', $visitor->id)
            ->where('beach_id', $beachId)
            ->exists();
    }

    public function addReaction(Visitor $visitor, int $beachId, string $reactionType): array
    {
        $availability = $this->reactionAvailability($visitor, $beachId);

        if (!$availability['can_react']) {
            return [
                'success' => false,
                'message' => 'Вы уже поставили реакцию. Следующую можно отправить позже.',
                ...$availability,
            ];
        }

        Reaction::query()->create([
            'beach_id' => $beachId,
            'visitor_id' => $visitor->id,
            'reaction_type' => $reactionType,
        ]);

        return [
            'success' => true,
            'message' => 'Реакция сохранена.',
            ...$this->reactionAvailability($visitor, $beachId),
        ];
    }
}
