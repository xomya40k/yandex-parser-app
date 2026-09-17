<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\Organization\SyncOrganizationDTO;
use App\Repositories\Interfaces\OrganizationRepositoryInterface;
use App\Services\Organization\OrganizationSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('yandex:sync-organizations')]
#[Description('Sync due Yandex Maps organizations (parse reviews, upsert, snapshot)')]
class SyncYandexOrganizationsCommand extends Command
{
    private const LOCK_TTL_SECONDS = 600;

    public function handle(
        OrganizationRepositoryInterface $organizationRepository,
        OrganizationSyncService $syncService,
    ): int {
        $reparseIntervalHours = (int) config('yandex.reparse_interval_hours', 24);
        $organizations = $organizationRepository->findDueForSync($reparseIntervalHours);

        if ($organizations->isEmpty()) {
            $this->components->info('No organizations due for sync.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Syncing %d organization(s)...', $organizations->count()));

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($organizations as $organization) {
            $lock = Cache::lock(
                "yandex-sync-organization-{$organization->id}",
                self::LOCK_TTL_SECONDS,
            );

            if (!$lock->get()) {
                $this->components->warn("Skipping organization #{$organization->id}: lock held.");
                $skipped++;

                continue;
            }

            try {
                $syncService->sync(new SyncOrganizationDTO($organization->id));
                $synced++;
                $this->components->info("Synced organization #{$organization->id}.");
            } catch (Throwable $exception) {
                $failed++;

                Log::error('yandex:sync-organizations failed for organization', [
                    'organization_id' => $organization->id,
                    'yandex_maps_url' => $organization->yandex_maps_url,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                $this->components->error(
                    "Failed organization #{$organization->id}: {$exception->getMessage()}",
                );
            } finally {
                $lock->release();
            }
        }

        $this->components->info(
            "Done. Synced: {$synced}, failed: {$failed}, skipped: {$skipped}.",
        );

        return self::SUCCESS;
    }
}
