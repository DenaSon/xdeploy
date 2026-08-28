<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Application\Server\Data\SupportHistoryEntryData;
use App\Domain\Server\Enums\SupportAccessAction;
use App\Models\Server;
use App\Models\SupportAccessLog;
use App\Models\User;
use LogicException;

final readonly class ListServerSupportHistoryAction
{
    /**
     * @param  list<SupportAccessAction>|null  $actions
     * @return list<SupportHistoryEntryData>
     */
    public function handle(
        User $admin,
        int $serverId,
        ?array $actions = null,
        int $limit = 20,
    ): array {
        if (! $admin->isAdmin()) {
            throw new LogicException(
                'Server support history is only available to administrators.',
            );
        }

        Server::withTrashed()
            ->whereKey($serverId)
            ->firstOrFail();

        if ($actions !== null) {
            foreach ($actions as $action) {
                if (! $action instanceof SupportAccessAction) {
                    throw new LogicException(
                        'Support history actions must be SupportAccessAction values.',
                    );
                }
            }
        }

        $query = SupportAccessLog::query()
            ->with('adminUser')
            ->where('server_id', $serverId)
            ->latest('id')
            ->limit(max(1, min($limit, 100)));

        if ($actions !== null) {
            $query->whereIn(
                'action',
                array_map(
                    static fn (SupportAccessAction $action): string => $action->value,
                    $actions,
                ),
            );
        }

        return $query
            ->get()
            ->map(
                static fn (SupportAccessLog $log): SupportHistoryEntryData => SupportHistoryEntryData::fromModel($log),
            )
            ->values()
            ->all();
    }
}
