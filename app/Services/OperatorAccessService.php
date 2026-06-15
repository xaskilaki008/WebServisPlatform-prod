<?php

namespace App\Services;

use App\Models\BeachOperator;
use App\Models\Beach;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OperatorAccessService
{
    /**
     * @return array<int>
     */
    public function currentOperatorBeachIds(Request $request): array
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin() && $user->is_active) {
            return Beach::query()
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $operator = $this->currentUnifiedOperator($request);

        if ($operator) {
            return $operator->beaches()
                ->orderBy('beaches.id')
                ->pluck('beaches.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $legacy = $this->currentLegacyOperator($request);

        return $legacy ? [(int) $legacy->beach_id] : [];
    }

    public function currentOperatorBeachId(Request $request): ?int
    {
        $beachIds = $this->currentOperatorBeachIds($request);

        return $beachIds[0] ?? null;
    }

    public function currentUnifiedOperator(Request $request): ?Operator
    {
        $user = $request->user();

        if (!$user instanceof User || !$user->isOperator() || !$user->is_active) {
            return null;
        }

        return $user->operator?->load('beaches');
    }

    public function currentLegacyOperator(Request $request): ?BeachOperator
    {
        $token = $request->cookie('operator_hash');

        if (!$token) {
            return null;
        }

        return BeachOperator::query()
            ->where('operator_hash', $token)
            ->first();
    }

    public function legacyOperatorForBeach(Request $request, int $beachId): ?BeachOperator
    {
        $legacy = $this->currentLegacyOperator($request);

        if ($legacy && (int) $legacy->beach_id === $beachId) {
            return $legacy;
        }

        $operator = $this->currentUnifiedOperator($request);
        $user = $request->user();

        if (!$operator || !$user instanceof User) {
            return null;
        }

        $hasBeach = $operator->beaches->contains(fn ($beach) => (int) $beach->id === $beachId);

        if (!$hasBeach) {
            return null;
        }

        return BeachOperator::query()
            ->where('login', $user->login)
            ->where('beach_id', $beachId)
            ->first();
    }

    public function compatibilityOperatorForBeach(Request $request, int $beachId): ?BeachOperator
    {
        $legacy = $this->legacyOperatorForBeach($request, $beachId);

        if ($legacy) {
            return $legacy;
        }

        $user = $request->user();

        if (!$user instanceof User) {
            return null;
        }

        if ($user->isAdmin() && $user->is_active) {
            return $this->compatibilityBeachOperatorForUser($user, $beachId, 'admin');
        }

        $operator = $this->currentUnifiedOperator($request);

        if (!$operator) {
            return null;
        }

        $hasBeach = $operator->beaches->contains(fn ($beach) => (int) $beach->id === $beachId);

        if (!$hasBeach) {
            return null;
        }

        return $this->compatibilityBeachOperatorForUser($user, $beachId, 'operator', $operator);
    }

    private function compatibilityBeachOperatorForUser(
        User $user,
        int $beachId,
        string $prefix,
        ?Operator $operator = null
    ): BeachOperator {
        $login = "{$prefix}-{$user->id}-beach-{$beachId}";

        return BeachOperator::query()->firstOrCreate(
            ['login' => $login],
            [
                'beach_id' => $beachId,
                'password' => Hash::make(Str::random(40)),
                'operator_hash' => Str::random(64),
                'name' => $user->full_name ?: $user->name,
                'last_name' => $user->last_name ?: '',
                'first_name' => $user->first_name ?: '',
                'middle_name' => $user->middle_name ?: '',
                'work_phone' => $operator?->work_phone ?: '00000000000',
            ]
        );
    }

    public function canAccessBeach(Request $request, int $beachId): bool
    {
        if ($this->legacyOperatorForBeach($request, $beachId)) {
            return true;
        }

        $user = $request->user();

        if ($user instanceof User && $user->isAdmin() && $user->is_active) {
            return true;
        }

        $operator = $this->currentUnifiedOperator($request);

        return $operator
            ? $operator->beaches->contains(fn ($beach) => (int) $beach->id === $beachId)
            : false;
    }
}
