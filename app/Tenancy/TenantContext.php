<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Business;
use Closure;

/**
 * Holds the business (tenant) the current request, job or command is acting for.
 *
 * Registered as a *scoped* singleton so it is reset between requests/jobs under
 * Octane or a long-running queue worker. It keeps a stack so nested `run()` calls
 * (e.g. a sync-queued job inside a request) restore the outer tenant afterwards.
 */
final class TenantContext
{
    /** @var list<Business|null> */
    private array $stack = [];

    private ?Business $current = null;

    public function set(?Business $business): void
    {
        $this->current = $business;
    }

    public function check(): bool
    {
        return $this->current !== null;
    }

    public function get(): Business
    {
        return $this->current ?? throw new MissingTenantException('No tenant is active.');
    }

    public function id(): ?int
    {
        return $this->current?->id;
    }

    public function push(?Business $business): void
    {
        $this->stack[] = $this->current;
        $this->current = $business;
    }

    public function pop(): void
    {
        $this->current = array_pop($this->stack);
    }

    /**
     * @template T
     *
     * @param  Closure(Business): T  $callback
     * @return T
     */
    public function run(Business $business, Closure $callback): mixed
    {
        $this->push($business);

        try {
            return $callback($business);
        } finally {
            $this->pop();
        }
    }
}
