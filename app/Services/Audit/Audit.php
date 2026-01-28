<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class Audit
{
    /**
     * Write an audit log entry.
     *
     * @param string $action
     * @param Model|null $subject
     * @param int|null $teamId
     * @param array $meta
     * @param string|null $actorType  user|system
     * @param int|null $actorId
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        ?int $teamId = null,
        array $meta = [],
        ?string $actorType = null,
        ?int $actorId = null,
    ): void {
        $actorType = $actorType ?: (auth()->check() ? 'user' : 'system');
        $actorId = $actorId ?: (auth()->check() ? (int) auth()->id() : null);

        $ip = null;
        $ua = null;

        try {
            $ip = Request::ip();
            $ua = Request::userAgent();
        } catch (\Throwable $e) {
            // likely in a job/CLI
        }

        AuditLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'team_id' => $teamId,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'meta' => $meta ?: null,
            'ip' => $ip,
            'user_agent' => $ua ? mb_substr($ua, 0, 255) : null,
            'created_at' => now(),
        ]);
    }
}