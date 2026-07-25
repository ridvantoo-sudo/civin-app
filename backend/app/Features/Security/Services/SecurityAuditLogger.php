<?php

namespace App\Features\Security\Services;

use App\Features\Security\Models\SecurityLog;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Append-only audit trail.
 *
 * Logging must never be able to fail a security analysis, so every write is
 * defensive: a broken log table degrades observability, not protection.
 */
final class SecurityAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function engine(string $action, string $message, array $context = [], ?string $userId = null): void
    {
        $this->write(SecurityLog::CHANNEL_ENGINE, 'info', $action, $message, $context, $userId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function decision(string $action, string $message, array $context = [], ?string $userId = null): void
    {
        $this->write(SecurityLog::CHANNEL_ENGINE, 'warning', $action, $message, $context, $userId);
    }

    /**
     * Administrative actions are always recorded with the acting admin, which
     * is what makes the panel auditable.
     *
     * @param  array<string, mixed>  $context
     */
    public function admin(
        string $action,
        string $message,
        ?string $actorId,
        ?string $userId = null,
        array $context = [],
    ): void {
        $this->write(SecurityLog::CHANNEL_ADMIN, 'notice', $action, $message, $context, $userId, $actorId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function api(string $action, string $message, ?string $userId, array $context = []): void
    {
        $this->write(SecurityLog::CHANNEL_API, 'info', $action, $message, $context, $userId);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function retention(string $action, string $message, array $context = []): void
    {
        $this->write(SecurityLog::CHANNEL_RETENTION, 'info', $action, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(
        string $channel,
        string $level,
        string $action,
        string $message,
        array $context = [],
        ?string $userId = null,
        ?string $actorId = null,
    ): void {
        try {
            SecurityLog::query()->create([
                'user_id' => $userId,
                'actor_id' => $actorId,
                'channel' => $channel,
                'level' => $level,
                'action' => mb_substr($action, 0, 96),
                'message' => mb_substr($message, 0, 500),
                'context' => $context,
                'created_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable) {
            // Intentionally swallowed — see class docblock.
        }
    }
}
