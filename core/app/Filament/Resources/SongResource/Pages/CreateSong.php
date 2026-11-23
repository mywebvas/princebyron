<?php

namespace App\Filament\Resources\SongResource\Pages;

use App\Filament\Resources\SongResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateSong extends CreateRecord
{
    protected static string $resource = SongResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        return $data;
    }
}
