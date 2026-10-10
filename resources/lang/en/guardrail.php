<?php

declare(strict_types=1);

return [
    'last_owner' => 'At least one Owner must remain.',
    'self_role_change' => "You can't change your own role.",
    'self_destructive' => "You can't suspend, deactivate or delete yourself.",
    'target_above_actor' => "You can't manage a user with more access than you have.",
    'escalation' => "You can't grant access higher than your own.",
    'high_risk_confirm_required' => 'This grants high-risk access. Confirm to continue.',
    'owner_role_locked' => "The Owner role always has full access and can't be edited.",
    'system_role' => "System roles can't be deleted or renamed.",
    'confirmation_mismatch' => 'Type the exact name to confirm.',
    'actor_required' => 'Authentication required.',
];
