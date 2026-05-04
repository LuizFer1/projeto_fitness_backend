<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    public function __construct(private Request $request) {}

    public function log(
        string $action,
        ?string $actorId = null,
        ?string $subjectId = null,
        ?string $subjectType = null,
        array $payload = [],
    ): void {
        try {
            AuditLog::create([
                'actor_id'     => $actorId,
                'subject_id'   => $subjectId,
                'subject_type' => $subjectType,
                'action'       => $action,
                'payload'      => empty($payload) ? null : $payload,
                'ip'           => $this->request->ip(),
                'user_agent'   => substr((string) $this->request->userAgent(), 0, 500),
                'request_id'   => $this->request->header('X-Request-Id'),
            ]);
        } catch (\Throwable $e) {
            Log::error('audit_log_failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
