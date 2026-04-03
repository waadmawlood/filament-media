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

        $this->multiple(fn (): bool => $this->resolveMultiple());

        $this->afterStateHydrated(fn (MediaUpload $component) => $component->hydrateMediaState());

        $this->getUploadedFileUsing(function (MediaUpload $component, string $file): ?array {
            $record = $component->getRecord();
            if (! $record || ! method_exists($record, 'getCollection')) {
                return null;
            }

            $media = $this->findMediaItem($record->getCollection($this->getCollection()), $file);
            if (! $media) {
                return null;
            }

            return [
                'name' => $media->filename ?? basename($file),
                'size' => $media->filesize ?? 0,
                'type' => $media->mimetype ?? null,
                'url' => $media->full_url ?? $media->url ?? '',
            ];
        });

        $this->deleteUploadedFileUsing(function (MediaUpload $component, string $file): void {
            $record = $component->getRecord();
            if ($record && method_exists($record, 'deleteMedia') && ! TemporaryUploadedFile::canUnserialize($file)) {
                $record->deleteMedia($file)->delete();
            }
        });

        $this->saveRelationshipsUsing(function (): void {
            if ($this->usesWaadMediaSync() && $this->getRecord()?->exists) {
                $this->syncWaadMediaToRecord();
            }
        });
    }

    protected function resolveMultiple(): bool
    {
        $modelClass = $this->getModel();
        if (! $modelClass || ! class_exists($modelClass) || ! method_exists(app($modelClass), 'registerCollections')) {
            return $this->isMultiple();
        }

        $collections = app($modelClass)->registerCollections();
        $name = $this->getCollection();

        return isset($collections[$name]) ? ! ($collections[$name]['single'] ?? true) : false;
    }

    protected function findMediaItem(Model|Collection|null $collection, string $file): ?Model
    {
        if ($collection instanceof Model) {
            return ((string) ($collection->uuid ?? $collection->id) === (string) $file) ? $collection : null;
        }

        if ($collection instanceof Collection) {
            return $collection->first(fn ($m) => (string) ($m->uuid ?? $m->id) === (string) $file);
        }

        return null;
    }

    protected function hydrateMediaState(): void
    {
        $record = $this->getRecord();
        if (! $record || ! method_exists($record, 'getCollection')) {
            $this->state([]);

            return;
        }

        $mediaItems = $record->getCollection($this->getCollection());

        if ($mediaItems instanceof Model) {
            $this->state([$mediaItems->uuid ?? $mediaItems->id]);
        } elseif ($mediaItems instanceof Collection) {
            $this->state($mediaItems->sortBy([
                fn ($a, $b) => ((int) ($a->index ?? 0)) <=> ((int) ($b->index ?? 0)),
                fn ($a, $b) => ((string) ($a->getKey() ?? '')) <=> ((string) ($b->getKey() ?? '')),
            ])->values()->map(fn ($m) => $m->uuid ?? $m->id)->toArray());
        } else {
            $this->state([]);
        }
    }

    public function saveUploadedFiles(): void
    {
        if (! $this->usesWaadMediaSync()) {
            parent::saveUploadedFiles();
        }
    }

    protected function usesWaadMediaSync(): bool
    {
        $class = $this->getModel();

        return is_string($class) && class_exists($class) && method_exists(app($class), 'syncMedia');
    }

    protected function syncWaadMediaToRecord(): void
    {
        $record = $this->getRecord();
        $collection = $this->getCollection();

        if (! $record || ! method_exists($record, 'syncMedia')) {
            return;
        }

        $state = $this->getState() ?? [];
        $state = is_array($state) ? array_values($state) : [$state];

        $filesToUpload = [];
        $existingMediaIdsToKeep = [];

        foreach ($state as $file) {
            $pending = $this->resolvePendingUploadsFromState($file);
            if ($pending !== []) {
                array_push($filesToUpload, ...$pending);
            } else {
                $existingMediaIdsToKeep[] = $file;
            }
        }

        if (! empty($filesToUpload)) {
            $files = count($filesToUpload) === 1 ? $filesToUpload[0] : $filesToUpload;
            $uploadResult = $record->syncMedia($files, [])
                ->setIsWithDettachedSync(false)
                ->collection($collection)
                ->upload();

            $orderedIds = $this->mergeUploadedMediaIntoStateOrder($state, $uploadResult);
            if ($orderedIds === []) {
                $this->hydrateMediaState();

                return;
            }

            $this->persistMediaOrderToIndexColumn($record, $collection, $orderedIds);
            $this->state($orderedIds);

            return;
        }

        $orderedIds = array_map(fn ($id) => (string) $id, $existingMediaIdsToKeep);
        $this->persistMediaOrderToIndexColumn($record, $collection, $orderedIds);
        $this->state($orderedIds);
    }

    protected function mergeUploadedMediaIntoStateOrder(array $state, Model|Collection|null $uploadResult): array
    {
        $uploadedList = $this->normalizeUploadResultToOrderedList($uploadResult);
        $u = 0;
        $out = [];

        foreach (array_values($state) as $file) {
            $pending = $this->resolvePendingUploadsFromState($file);
            if ($pending !== []) {
                foreach ($pending as $_) {
                    if (! isset($uploadedList[$u])) {
                        return [];
                    }
                    $out[] = (string) $uploadedList[$u]->id;
                    $u++;
                }
            } else {
                $out[] = (string) $file;
            }
        }

        return $u === count($uploadedList) ? $out : [];
    }

    protected function normalizeUploadResultToOrderedList(Model|Collection|null $uploadResult): array
    {
        if ($uploadResult === null) {
            return [];
        }

        $mediaModel = config('media.model', Model::class);
        if ($uploadResult instanceof $mediaModel) {
            return [$uploadResult];
        }

        return $uploadResult->values()->filter()->all();
    }

    protected function persistMediaOrderToIndexColumn(Model $record, string $collection, array $orderedIds): void
    {
        if (count($orderedIds) <= 1) {
            return;
        }

        foreach (array_values($orderedIds) as $position => $rawId) {
            if (TemporaryUploadedFile::canUnserialize($rawId)) {
                continue;
            }

            $id = is_numeric($rawId) ? (int) $rawId : $rawId;
            $media = $record->media()->where('collection', $collection)->whereKey($id)->first();

            if ($media) {
                $media->update(['index' => $position + 1]);
            }
        }
    }

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
                    ->filter(fn ($item): bool => $item instanceof TemporaryUploadedFile)
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    public static function make(?string $name = null): static
    {
        return parent::make($name)->collection($name);
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function getCollection(): string
    {
        return $this->evaluate($this->collection) ?? $this->getName();
    }
}
