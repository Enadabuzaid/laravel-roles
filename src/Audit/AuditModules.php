<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Audit;

use Enadstack\ActionLogger\Support\Redactor;
use Enadstack\LaravelRoles\Users\Models\AccessInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The vocabulary of the audit read API: filterable modules, the subject types the UI links to, the event keys
 * the package writes and the redaction applied on read.
 */
final class AuditModules
{
    /** Every `<module>.<verb>` key the package records; anything else summarizes as `generic`. */
    public const EVENTS = [
        'users.updated', 'users.suspended', 'users.reactivated', 'users.deactivated', 'users.unlocked', 'users.activated',
        'users.password_reset_forced', 'users.email_verified', 'users.verification_resent',
        'users.trashed', 'users.restored', 'users.deleted', 'users.force_deleted',
        'invitations.created', 'invitations.resent', 'invitations.revoked', 'invitations.accepted', 'invitations.revoked_role_removed', 'invitations.revoked_unauthorized',
        'roles.created', 'roles.updated', 'roles.trashed', 'roles.restored', 'roles.deleted', 'roles.seeded',
        'roles.assigned', 'roles.removed', 'roles.synced', 'roles.permissions_updated',
        'permissions.created', 'permissions.updated', 'permissions.deleted', 'permissions.synced',
        'audit.exported',
    ];

    /** @return list<string> */
    public static function modules(): array
    {
        return array_values(array_unique(array_filter(
            (array) config('roles.audit.modules', []),
            static fn (mixed $module): bool => is_string($module) && $module !== '',
        )));
    }

    /**
     * Subject type name => model class.
     *
     * @return array<string, class-string<Model>>
     */
    public static function subjectTypes(): array
    {
        $map = [
            'user' => (string) config('roles.user_model'),
            'role' => (string) config('permission.models.role'),
            'permission' => (string) config('permission.models.permission'),
            'invitation' => AccessInvitation::class,
        ];

        return array_filter($map, static fn (string $class): bool => $class !== '' && is_subclass_of($class, Model::class));
    }

    /**
     * Stored morph strings of a subject type: the class and its morph alias (the log may hold either).
     *
     * @return list<string>
     */
    public static function storedTypes(string $name): array
    {
        $class = self::subjectTypes()[$name] ?? null;

        if ($class === null) {
            return [];
        }

        return array_values(array_unique([$class, (new $class)->getMorphClass()]));
    }

    /** The UI name of a stored morph string: user, role, permission, invitation or other. */
    public static function typeName(?string $stored): ?string
    {
        if ($stored === null) {
            return null;
        }

        foreach (array_keys(self::subjectTypes()) as $name) {
            if (in_array($stored, self::storedTypes($name), true)) {
                return $name;
            }
        }

        return 'other';
    }

    /** @return class-string<Model>|null */
    public static function modelClass(string $stored): ?string
    {
        $class = Relation::getMorphedModel($stored) ?? $stored;

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    public static function isKnownEvent(string $event): bool
    {
        return in_array($event, self::EVENTS, true);
    }

    public static function redactor(): Redactor
    {
        $keys = config('roles.audit.redact');

        if (! is_array($keys)) {
            $keys = (array) config('action-logger.redact', []);
        }

        return new Redactor(array_values(array_filter($keys, 'is_string')));
    }
}
