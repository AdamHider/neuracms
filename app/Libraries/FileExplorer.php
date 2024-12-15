<?php 

namespace App\Libraries;
class FileExplorer 
{
    public function listFiles($basePath, $dir)
    {
        $path = $dir ? $basePath . $dir : $basePath;

        $files = directory_map($path, 1);
        $folders = array_filter($files, function($f) use($path) {
            return is_dir($path.'/'.$f);
        });
        $files = array_filter($files, function($f) use($path) {
            return  is_file($path.'/'.$f);
        });
        return array_merge($folders, $files);
    }
}