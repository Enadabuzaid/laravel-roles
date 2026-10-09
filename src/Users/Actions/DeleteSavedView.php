<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Actions;

use Enadstack\LaravelRoles\Users\Models\SavedView;

final class DeleteSavedView
{
    public function handle(SavedView $view): void
    {
        $view->delete();
    }
}
