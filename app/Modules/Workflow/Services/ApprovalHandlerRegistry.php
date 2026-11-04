<?php

namespace App\Modules\Workflow\Services;

use App\Modules\Workflow\Contracts\ApprovalHandlerInterface;
use App\Modules\Workflow\Models\ApprovalRequest;

/**
 * [Phase 2 / T-07] Registry for domain-specific approval handlers.
 * Allows modules to register their execution handlers without coupling ApprovalService to concrete entities.
 */
class ApprovalHandlerRegistry
{
    /**
     * @var array<int, ApprovalHandlerInterface>
     */
    protected array $handlers = [];

    public function register(ApprovalHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function resolve(string $entityType): ?ApprovalHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($entityType)) {
                return $handler;
            }
        }

        return null;
    }

    public function handle(ApprovalRequest $request, string $status, ?int $actionedBy, ?string $catatan, array $options = []): bool
    {
        $handler = $this->resolve($request->entity_type);
        if ($handler) {
            $handler->handle($request, $status, $actionedBy, $catatan, $options);

            return true;
        }

        return false;
    }
}
