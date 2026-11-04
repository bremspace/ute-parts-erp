<?php

namespace App\Modules\Workflow\Contracts;

use App\Modules\Workflow\Models\ApprovalRequest;

/**
 * [Phase 2 / T-07] Interface for modular, decoupled entity approval handlers.
 */
interface ApprovalHandlerInterface
{
    /**
     * Check if this handler supports the given entity type.
     */
    public function supports(string $entityType): bool;

    /**
     * Execute business logic upon approval or rejection.
     *
     * @param  string  $status  'disetujui' | 'ditolak'
     */
    public function handle(ApprovalRequest $request, string $status, ?int $actionedBy, ?string $catatan, array $options = []): void;
}
