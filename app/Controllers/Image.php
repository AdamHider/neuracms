<?php

namespace App\Controllers;
class Image extends BaseController
{
    public function index($path = '')
    {
        // Декодируем URL, чтобы получить корректный путь
        $path = urldecode($path);

        // Путь к изображению
        $fullPath = WRITEPATH . 'uploads/media/' . $path;

        // Проверяем, существует ли файл
        if (file_exists($fullPath)) {
            $mimeType = mime_content_type($fullPath);
            header("Content-Type: $mimeType");
            readfile($fullPath);
            exit;
        } else {
            return redirect()->to('/')->with('error', 'File not found.');
        }
    }
}
