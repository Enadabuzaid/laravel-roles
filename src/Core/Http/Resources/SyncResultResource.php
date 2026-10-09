<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Core\Http\Resources;

use Enadstack\LaravelRoles\Core\Actions\SyncResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SyncResultResource extends JsonResource
{
    public function __construct(SyncResult $result, private readonly bool $dryRun)
    {
        parent::__construct($result);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $result = $this->resource;

        if (! $result instanceof SyncResult) {
            throw new \LogicException('SyncResultResource wraps a SyncResult.');
        }

        return [
            'added' => $result->added,
            'updated' => $result->updated,
            'restored' => $result->restored,
            'deprecated' => $result->deprecated,
            'pruned' => $result->pruned,
            'has_changes' => $result->hasChanges(),
            'dry_run' => $this->dryRun,
        ];
    }
}
