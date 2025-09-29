<?php


namespace Enadabuzaid\LaravelRoles\Providers;

use Enadabuzaid\LaravelRoles\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class RolesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-roles')     // used for publish tags
            ->hasConfigFile('roles')
            ->hasTranslations()
            ->hasMigrations()
            ->hasCommand(InstallCommand::class);
    }
}