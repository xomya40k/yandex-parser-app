<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\Parsing\ParseRunTargetDTO;
use App\Repositories\Interfaces\OrganizationRepositoryInterface;
use App\Services\Parsing\ParseRunService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('yandex:queue-parsing')]
#[Description('Reap stale parse runs and queue parsing jobs for due organizations')]
class QueueOrganizationParsingCommand extends Command
{
    public function handle(
        OrganizationRepositoryInterface $organizationRepository,
        ParseRunService $parseRunService,
    ): int {
        $reaped = $parseRunService->reapStale();

        if ($reaped->count > 0) {
            $this->components->info("Reaped {$reaped->count} stale parse run(s).");
        }

        $reparseIntervalHours = (int) config('yandex.reparse_interval_hours', 24);
        $organizations = $organizationRepository->findDueForSync($reparseIntervalHours);

        if ($organizations->isEmpty()) {
            $this->components->info('No organizations due for sync.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Queueing %d organization(s)...', $organizations->count()));

        $spacing = (int) config('yandex.queue.dispatch_spacing_seconds', 10);
        $queued = 0;

        foreach ($organizations->values() as $index => $organization) {
            $run = $parseRunService->queue(new ParseRunTargetDTO(
                organizationId: $organization->id,
                delaySeconds: $index * $spacing,
            ));

            $queued++;
            $this->components->info(
                "Queued organization #{$organization->id} (run #{$run->parseRun->id}).",
            );
        }

        $this->components->info("Done. Queued: {$queued}.");

        return self::SUCCESS;
    }
}
