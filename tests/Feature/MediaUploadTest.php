<?php

use Filament\Forms\Components\FileUpload;
use Waad\FilamentMedia\Forms\Components\MediaUpload;
use Waad\FilamentMedia\Tests\Feature\TestModel;

it('configures single upload based on registerCollections', function () {
    $component = MediaUpload::make('avatar')->model(TestModel::class);

    // We expect multiple to be false because 'single' => true in registerCollections
    expect($component->isMultiple())->toBeFalse();
});

it('configures multiple upload based on registerCollections', function () {
    $component = MediaUpload::make('gallery')->model(TestModel::class);

    // We expect multiple to be true because 'single' => false in registerCollections
    expect($component->isMultiple())->toBeTrue();
});

it('sets correct collection name', function () {
    $component = MediaUpload::make('avatar')->model(TestModel::class);

    expect($component->getCollection())->toBe('avatar');
});

it('uses name as default collection when not explicitly set', function () {
    $component = MediaUpload::make('custom')->model(TestModel::class);

    expect($component->getCollection())->toBe('custom');
});

it('allows explicit collection override using string', function () {
    $component = MediaUpload::make('avatar')
        ->model(TestModel::class)
        ->collection('thumbnails');

    expect($component->getCollection())->toBe('thumbnails');
});

it('allows explicit collection override using closure', function () {
    $component = MediaUpload::make('avatar')
        ->model(TestModel::class)
        ->collection(fn () => 'dynamic_collection');

    expect($component->getCollection())->toBe('dynamic_collection');
});

it('extends Filament FileUpload component', function () {
    $component = MediaUpload::make('avatar');

    expect($component)->toBeInstanceOf(FileUpload::class);
});
