<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Beach;
use Illuminate\Support\Facades\Storage;

class BeachController extends Controller
{
    public function getInfo($id)
    {
        $beach = Beach::findOrFail($id);

        return response()->json([
            'id' => $beach->id,
            'name' => $beach->name,
            'wave_level_text' => $beach->wave_level_text,
            'category_label' => $beach->category_label,
            'wave_level' => $beach->wave_level,
            // Добавь сюда любые другие поля, которые нужны на фронтенде
        ]);
    }

    public function getPhoto($id)
    {
        $directory = 'public/фотографии пляжей';
        $files = Storage::files($directory);

        // Ищем фото, начинающиеся с ID пляжа
        $beachPhotos = array_filter($files, function ($file) use ($id) {
            return str_starts_with(basename($file), $id . '-');
        });

        $urls = array_map(function ($file) {
            return Storage::url($file);
        }, $beachPhotos);

        return response()->json(['urls' => array_values($urls)]);
    }
}