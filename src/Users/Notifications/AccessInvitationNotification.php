<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Users\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

/**
 * The invitation mail. Deliberately NOT queued: a queued notification would persist the plain token in the
 * queue payload. It is the only place the plain token ever appears.
 */
final class AccessInvitationNotification extends Notification
{
    public function __construct(
        #[SensitiveParameter] private readonly string $token,
        private readonly string $email,
        private readonly ?string $roleName,
        private readonly int $ttlHours,
    ) {}

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject((string) trans('access::invitation.subject', ['app' => (string) config('app.name')]))
            ->greeting((string) trans('access::invitation.greeting'))
            ->line((string) trans('access::invitation.intro', ['app' => (string) config('app.name')]));

        if ($this->roleName !== null) {
            $message->line((string) trans('access::invitation.role', ['role' => $this->roleName]));
        }

        return $message
            ->action((string) trans('access::invitation.action'), $this->acceptUrl())
            ->line((string) trans('access::invitation.expires', ['hours' => $this->ttlHours]))
            ->line((string) trans('access::invitation.ignore'));
    }

    public function acceptUrl(): string
    {
        $base = config('roles.users.accept_url');
        $base = is_string($base) && $base !== '' ? $base : url('/accept-invitation');

        return $base.(str_contains($base, '?') ? '&' : '?').http_build_query(['token' => $this->token, 'email' => $this->email], '', '&', PHP_QUERY_RFC3986);
    }
}
