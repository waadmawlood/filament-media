<?php

namespace Waad\FilamentMedia\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Waad\Media\HasMedia;

class TestModel extends Model
{
    use HasMedia;

    protected $table = 'waad_filament_media_tests';

    protected $guarded = [];

    public function registerCollections(array $attributes = []): array
    {
        return [
            'avatar' => [
                'disk' => 'local',
                'bucket' => 'avatars',
                'single' => true,
            ],
            'gallery' => [
                'disk' => 'local',
                'bucket' => 'photos',
                'single' => false,
            ],
        ];
    }
}
