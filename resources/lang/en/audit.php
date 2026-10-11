<?php

declare(strict_types=1);

return [
    'system' => 'System',
    'target_gone' => 'No longer exists',
    'redacted' => 'Hidden',
    'export_too_large' => 'This export has :count rows, above the limit of :max. Narrow the filters and try again.',
    'csv' => [
        'time' => 'Time',
        'actor' => 'Actor',
        'module' => 'Module',
        'event' => 'Action',
        'summary' => 'Summary',
        'target' => 'Target',
        'severity' => 'Severity',
        'ip' => 'IP',
        'changes' => 'Changes',
    ],
    'events' => [
        'users' => [
            'updated' => ':actor edited :subject',
            'suspended' => ':actor suspended :subject',
            'reactivated' => ':actor reactivated :subject',
            'deactivated' => ':actor deactivated :subject',
            'unlocked' => ':actor unlocked :subject',
            'activated' => ':subject accepted the invitation and became active',
            'password_reset_forced' => ':actor forced a password reset for :subject',
            'email_verified' => ':actor verified the email of :subject',
            'verification_resent' => ':actor resent the verification email to :subject',
            'trashed' => ':actor moved :subject to the trash',
            'restored' => ':actor restored :subject from the trash',
            'deleted' => ':actor deleted :subject',
            'force_deleted' => ':actor permanently deleted :subject',
        ],
        'invitations' => [
            'created' => ':actor invited :subject',
            'resent' => ':actor resent the invitation to :subject',
            'revoked' => ':actor revoked the invitation of :subject',
            'accepted' => ':actor accepted the invitation',
            'revoked_role_removed' => 'Invitations were revoked because a role was removed',
            'revoked_unauthorized' => 'Invitations were revoked because the inviter lost the authority to send them',
        ],
        'roles' => [
            'created' => ':actor created the role :subject',
            'updated' => ':actor edited the role :subject',
            'trashed' => ':actor moved the role :subject to the trash',
            'restored' => ':actor restored the role :subject',
            'deleted' => ':actor deleted the role :subject',
            'seeded' => 'Default roles were seeded',
            'assigned' => ':actor assigned a role to :subject',
            'removed' => ':actor removed a role from :subject',
            'synced' => ':actor changed the roles of :subject',
            'permissions_updated' => ':actor changed the permissions of :subject',
        ],
        'permissions' => [
            'created' => ':actor created the permission :subject',
            'updated' => ':actor edited the permission :subject',
            'deleted' => ':actor deleted the permission :subject',
            'synced' => ':actor synced the permissions',
        ],
        'audit' => [
            'exported' => ':actor exported the audit log',
        ],
        'generic' => ':actor performed :event on :subject',
    ],
];
