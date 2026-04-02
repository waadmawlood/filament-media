![Logo](image.png)

# Filament Media

This package provides a seamless integration between [Filament](https://filamentphp.com) v3/v4/v5 and the [waad/media](https://github.com/waadmawlood/media) manager package. It's Alternative to `spatie/laravel-medialibrary` It introduces a `MediaUpload` form component that dynamically builds itself based on your Eloquent Model's `registerCollections()` method.

## Requirements

- PHP: `^8.2`
- Filament: `^3.0 || ^4.0 || ^5.0`
- [waad/media](https://github.com/waadmawlood/media): `^4.1`

## Installation

You can install the package via composer:

```bash
composer require waad/filament-media
```

## Setup & Usage

Since this package works in tandem with `waad/media`, your Eloquent Models should use the `HasMedia` trait and implement `registerCollections`: [waad/media](https://github.com/waadmawlood/media) Package.

```bash
php artisan vendor:publish --provider="Waad\Media\MediaServiceProvider"
```

```php
use Illuminate\Database\Eloquent\Model;
use Waad\Media\HasMedia;

class Post extends Model
{
    use HasMedia;

    public function registerCollections(array $attributes = []): array
    {
        return [
            'avatar' => [
                'disk' => 's3',
                'bucket' => 'avatars',
                'label' => 'User Avatar',
                'single' => true, // Only keeps one file
            ],
            'gallery' => [
                'disk' => 'public',
                'bucket' => 'photos',
                'label' => 'Photo Gallery',
                'single' => false, // Allows multiple files
            ],
        ];
    }
}
```

Now, in your Filament Resource or Form, simply use `MediaUpload::make('collection_name')`:

```php
use Waad\FilamentMedia\Forms\Components\MediaUpload;

public static function form(Form $form): Form
{
    return $form
        ->schema([
            // This will automatically act as a single file upload because 'single' is true in registerCollections
            MediaUpload::make('avatar') // name collection,

            // This will automatically act as a multiple file upload because 'single' is false in registerCollections
            MediaUpload::make('gallery') // name collection
                ->collection('gallery')  // name collection
                ->image()
                ->maxSize(2048),
            
            // It Select Multiple Files or Single File Automatically Based on `single` property in `registerCollections`
            // Can Use Other Methods of Filament `FileUpload` Component!
        ]);
}
```

The component automatically handles fetching current file URLs for previews and syncing modifications (adding and deleting) directly supplied by `waad/media`.

## Testing

This package uses Pest for testing. To run tests:

```bash
composer test
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
