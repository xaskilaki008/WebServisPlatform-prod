<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActionLog;
use Illuminate\Http\Request;

class UserActionLogger
{
    public function log(
        Request $request,
        string $action,
        ?User $user = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null
    ): void {
        UserActionLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
