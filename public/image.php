<?php
// Получаем путь к изображению из URL
$imagePath = $_GET['path'] ?? '';

if ($imagePath) {
    $fullPath = WRITEPATH . 'uploads/media/' . $imagePath;

    // Проверяем, существует ли файл
    if (file_exists($fullPath)) {
        $mimeType = mime_content_type($fullPath);
        header("Content-Type: $mimeType");
        readfile($fullPath);
        exit;
    } else {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }
} else {
    http_response_code(400);
    echo 'Bad request.';
    exit;
}
