<?php

namespace Waad\FilamentMedia\Tests;

use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Waad\FilamentMedia\FilamentMediaServiceProvider;
use Waad\Media\MediaServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            SupportServiceProvider::class,
            FormsServiceProvider::class,
            FilamentServiceProvider::class,
            MediaServiceProvider::class, // waad/media
            FilamentMediaServiceProvider::class, // our package
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Allow media package to work with testing disk if needed
        config()->set('media.disk', 'local');
    }
}
