<?php

declare(strict_types=1);

namespace App\Repositories\Interfaces;

use App\Models\ParseRun;
use Carbon\CarbonInterface;

interface ParseRunRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ParseRun;

    /**
     * Id-based update so a long-running job never overwrites progress
     * written by the listener from a stale in-memory model.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateById(int $id, array $data): ?ParseRun;

    public function findById(int $id): ?ParseRun;

    public function findLatestForOrganization(int $organizationId): ?ParseRun;

    /**
     * Active run for an organization (status pending or processing) — the dedupe primitive.
     */
    public function findActiveForOrganization(int $organizationId): ?ParseRun;

    /**
     * Mark processing runs older than $cutoff as failed. Returns the number of rows updated.
     */
    public function markStaleAsFailed(CarbonInterface $cutoff, string $errorCode): int;
}
