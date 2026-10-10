<?php

declare(strict_types=1);

return [
    'invitation_invalid' => 'This invitation is invalid or has expired.',
    'invitation_user_exists' => 'A user with this email already exists.',
    'invitation_pending_exists' => 'A pending invitation for this email already exists. Resend it instead.',
    'invitation_not_pending' => 'This invitation was already accepted or revoked.',
    'invalid_transition' => "A :status user can't be :action.",
    'saved_view_limit' => 'You can save up to :max views per page. Delete one first.',
    'last_owner_delete' => 'At least one Owner must remain.',
    'status' => [
        'invited' => 'invited',
        'pending_verification' => 'pending verification',
        'active' => 'active',
        'locked' => 'locked',
        'suspended' => 'suspended',
        'deactivated' => 'deactivated',
    ],
    'action' => [
        'suspended' => 'suspended',
        'reactivated' => 'reactivated',
        'deactivated' => 'deactivated',
        'unlocked' => 'unlocked',
    ],
];
