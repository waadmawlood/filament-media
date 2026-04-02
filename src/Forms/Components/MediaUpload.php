<?php

namespace Waad\FilamentMedia\Forms\Components;

use Closure;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class MediaUpload extends FileUpload
{
    protected string|Closure|null $collection = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure multiple/single based on model's registerCollections (lazy evaluation via closure)
        $this->multiple(function (): bool {
            $modelClass = $this->getModel();
            if (! $modelClass || ! class_exists($modelClass)) {
                return $this->isMultiple(); // default false
            }

            $modelInstance = new $modelClass;
            if (! method_exists($modelInstance, 'registerCollections')) {
                return $this->isMultiple(); // default false
            }

            $collectionName = $this->getCollection();
            $collectionsInfo = $modelInstance->registerCollections();

            if (! isset($collectionsInfo[$collectionName])) {
                return false;
            }

            return ! ($collectionsInfo[$collectionName]['single'] ?? true);
        });

        // Load state from model's media collection (hydrated)
        $this->afterStateHydrated(function (MediaUpload $component): void {
            $component->hydrateMediaState();
        });

        // Custom getUploadedFileUsing for fetching URLs from waad/media
        $this->getUploadedFileUsing(function (MediaUpload $component, string $file, string|array|null $storedFileNames): ?array {
            $record = $component->getRecord();

            if (! $record || ! method_exists($record, 'getCollection')) {
                return null;
            }

            $mediaCollection = $record->getCollection($component->getCollection());

            if ($mediaCollection instanceof Model) {
                // Single media
                if ((string) ($mediaCollection->uuid ?? $mediaCollection->id) === (string) $file) {
                    return [
                        'name' => $mediaCollection->filename ?? basename($file),
                        'size' => $mediaCollection->filesize ?? 0,
                        'type' => $mediaCollection->mimetype ?? null,
                        'url' => $mediaCollection->full_url ?? $mediaCollection->url ?? '',
                    ];
                }
            } elseif ($mediaCollection instanceof Collection) {
                $mediaItem = $mediaCollection->first(function ($media) use ($file) {
                    return (string) ($media->uuid ?? $media->id) === (string) $file;
                });

                if ($mediaItem) {
                    return [
                        'name' => $mediaItem->filename ?? basename($file),
                        'size' => $mediaItem->filesize ?? 0,
                        'type' => $mediaItem->mimetype ?? null,
                        'url' => $mediaItem->full_url ?? $mediaItem->url ?? '',
                    ];
                }
            }

            return null;
        });

        // Delete uploaded file
        $this->deleteUploadedFileUsing(function (MediaUpload $component, string $file): void {
            $record = $component->getRecord();

            if ($record && method_exists($record, 'deleteMedia')) {
                if (TemporaryUploadedFile::canUnserialize($file)) {
                    return;
                }

                $record->deleteMedia($file)->delete();
            }
        });
    }

    /**
     * Hydrate state with existing media IDs/UUIDs from the model
     */
    protected function hydrateMediaState(): void
    {
        $record = $this->getRecord();
        $collection = $this->getCollection();

        if (! $record || ! method_exists($record, 'getCollection')) {
            $this->state([]);

            return;
        }

        $mediaItems = $record->getCollection($collection);

        if ($mediaItems instanceof Model) {
            // Single media returns a Model instance
            $id = $mediaItems->uuid ?? $mediaItems->id;
            $this->state([$id]);
        } elseif ($mediaItems instanceof Collection) {
            // Multiple media returns a Collection
            $state = $mediaItems->map(function ($media) {
                return $media->uuid ?? $media->id;
            })->toArray();
            $this->state($state);
        } else {
            $this->state([]);
        }
    }

    /**
     * Override saveUploadedFiles to use waad/media syncMedia
     * Deletion only happens on save (not on cancel)
     */
    public function saveUploadedFiles(): void
    {
        $record = $this->getRecord();
        $collection = $this->getCollection();

        if (! $record || ! method_exists($record, 'syncMedia')) {
            parent::saveUploadedFiles();

            return;
        }

        $state = $this->getState() ?? [];
        $state = is_array($state) ? array_values($state) : [$state];

        $filesToUpload = [];
        $existingMediaIdsToKeep = [];

        foreach ($state as $file) {
            $pendingUploads = $this->resolvePendingUploadsFromState($file);

            if ($pendingUploads !== []) {
                foreach ($pendingUploads as $upload) {
                    $filesToUpload[] = $upload;
                }
            } else {
                $existingMediaIdsToKeep[] = $file;
            }
        }

        // Upload new files only
        if (! empty($filesToUpload)) {
            $files = count($filesToUpload) === 1 ? $filesToUpload[0] : $filesToUpload;

            $record->syncMedia($files, [])
                ->setIsWithDettachedSync(false)
                ->collection($collection)
                ->upload();
        }

        $this->state(array_map(fn ($id) => (string) $id, $existingMediaIdsToKeep));
    }

    /**
     * @return array<int, TemporaryUploadedFile>
     */
    protected function resolvePendingUploadsFromState(mixed $file): array
    {
        if ($file instanceof TemporaryUploadedFile) {
            return [$file];
        }

        if (TemporaryUploadedFile::canUnserialize($file)) {
            $resolved = TemporaryUploadedFile::unserializeFromLivewireRequest($file);

            if ($resolved instanceof TemporaryUploadedFile) {
                return [$resolved];
            }

            if (is_array($resolved)) {
                return collect($resolved)
                    ->flatten()
                    ->filter(fn (mixed $item): bool => $item instanceof TemporaryUploadedFile)
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    public static function make(?string $name = null): static
    {
        $static = parent::make($name);
        $static->collection($name);

        return $static;
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function getCollection(): string
    {
        if ($this->collection !== null && ! ($this->collection instanceof Closure)) {
            return $this->collection;
        }

        return $this->evaluate($this->collection) ?? $this->getName();
    }
}
