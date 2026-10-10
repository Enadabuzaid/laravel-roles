<?php

declare(strict_types=1);

return [
    'in_use' => "This user is still referenced by other records and can't be deleted. Deactivate the user instead.",
    'not_trashed' => 'This user is not in the trash.',
    'trashed' => 'This user is in the trash. Restore the user first.',
    'email_taken' => 'Another account already uses this email address, so the user cannot be restored.',
    'self_email_change' => 'You cannot change your own email address here. Use your account settings instead.',
    'already_verified' => "This user's email address is already verified.",
    'verification_unsupported' => 'This application does not use email verification.',
    'verification_throttled' => 'Too many verification emails were requested for this user. Try again later.',
];
