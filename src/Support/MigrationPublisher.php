<?php

declare(strict_types=1);

namespace Enadstack\LaravelRoles\Support;

use DateTimeImmutable;
use Illuminate\Filesystem\Filesystem;

/**
 * Publishes the extension migration stubs with install-time timestamps so they
 * always sort after the host project's Spatie permission tables migration.
 */
class MigrationPublisher
{
    /** @var array<string, string> stub file => migration name */
    public const STUBS = [
        'extend_roles_table.php.stub' => 'extend_roles_table',
        'extend_permissions_table.php.stub' => 'extend_permissions_table',
        'create_user_access_states_table.php.stub' => 'create_user_access_states_table',
        'create_access_invitations_table.php.stub' => 'create_access_invitations_table',
        'create_access_saved_views_table.php.stub' => 'create_access_saved_views_table',
    ];

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $targetDirectory,
    ) {}

    /**
     * Source stub path => destination path, with timestamps resolved now.
     *
     * @return array<string, string>
     */
    public function targets(): array
    {
        $base = max(time(), $this->latestSpatieTimestamp() + 1);
        $targets = [];
        $offset = 0;

        foreach (self::STUBS as $stub => $name) {
            $existing = $this->publishedFiles($name);

            $targets[__DIR__.'/../../database/migrations/'.$stub] = $existing !== []
                ? $existing[0]
                : $this->targetDirectory.'/'.date('Y_m_d_His', $base + $offset).'_'.$name.'.php';
            $offset++;
        }

        return $targets;
    }

    /**
     * Copy the stubs that are not published yet.
     *
     * @return list<string> destination paths that were written
     */
    public function publish(): array
    {
        $this->files->ensureDirectoryExists($this->targetDirectory);
        $written = [];

        foreach ($this->targets() as $source => $destination) {
            if (! $this->files->exists($destination)) {
                $this->files->copy($source, $destination);
                $written[] = $destination;
            }
        }

        return $written;
    }

    /**
     * Files in the target directory named exactly `<timestamp>_<name>.php`.
     *
     * @return list<string>
     */
    private function publishedFiles(string $name): array
    {
        $pattern = '/^\d{4}_\d{2}_\d{2}_\d{6}_'.preg_quote($name, '/').'\.php$/';

        return array_values(array_filter(
            $this->files->glob($this->targetDirectory.'/*_'.$name.'.php'),
            fn (string $file): bool => preg_match($pattern, basename($file)) === 1,
        ));
    }

    private function latestSpatieTimestamp(): int
    {
        $latest = 0;

        foreach ($this->publishedFiles('create_permission_tables') as $file) {
            $date = DateTimeImmutable::createFromFormat('Y_m_d_His', substr(basename($file), 0, 17));

            if ($date !== false) {
                $latest = max($latest, $date->getTimestamp());
            }
        }

        return $latest;
    }
}
