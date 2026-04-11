<?php

namespace Waad\FilamentMedia;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentMediaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('waad-filament-media');
    }

    public function boot(): void
    {
        parent::boot();
    }
}
