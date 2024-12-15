<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
class Media extends BaseController
{
    protected $baseMediaPath = WRITEPATH . 'uploads/media/';

    public function index()
    {
        $data['settings'] = [
            'layout' => 'admin',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Media',
            'path' => '/admin/media'
        ];
        return view('admin/media/index', $data);
    }
    public function listFiles()
    {
        $dir = $this->request->getGet('dir') ?? '';
        $dir = trim($dir, '/');
        $path = $dir ? $this->baseMediaPath . $dir : $this->baseMediaPath;

        $files = directory_map($path, 1);
        $folders = array_filter($files, function($f) use($path) {
            return is_dir($path.'/'.$f);
        });
        $files = array_filter($files, function($f) use($path) {
            return  is_file($path.'/'.$f);
        });
       
        return view('admin/media/_file_explorer_directory', ['currentDir' => $dir, 'files' => array_merge($folders, $files)]);
        //return $this->response->setJSON(['currentDir' => $dir, 'files' => array_merge($folders, $files)]);
    }

    public function upload()
    {
        $dir = $this->request->getPost('dir') ?? '';
        $dir = trim($dir, '/');
        $path = $dir ? $this->baseMediaPath . $dir : $this->baseMediaPath;

        $file = $this->request->getFile('file');
        if ($file->isValid() && !$file->hasMoved()) {
            $file->move($path);
            return $this->response->setJSON(['status' => 'success', 'message' => 'File uploaded successfully']);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'File upload failed']);
    }

    public function createDirectory()
    {
        $dir = $this->request->getPost('dir') ?? '';
        $dir = trim($dir, '/');
        $path = $dir ? $this->baseMediaPath . $dir : $this->baseMediaPath;

        $newDirName = $this->request->getPost('dirname');
        $newDirPath = $path . '/' . $newDirName;

        if (!is_dir($newDirPath)) {
            mkdir($newDirPath, 0777, true);
            return $this->response->setJSON(['status' => 'success', 'message' => 'Directory created successfully']);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Directory creation failed']);
    }

    public function rename()
    {
        $dir = $this->request->getPost('dir') ?? '';
        $dir = trim($dir, '/');
        $path = $dir ? $this->baseMediaPath . $dir : $this->baseMediaPath;

        $oldName = $this->request->getPost('oldName');
        $newName = $this->request->getPost('newName');
        $oldPath = $path . '/' . $oldName;
        $newPath = $path . '/' . $newName;

        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
            return $this->response->setJSON(['status' => 'success', 'message' => 'Renamed successfully']);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Rename failed']);
    }

    public function delete()
    {
        $dir = $this->request->getPost('dir') ?? '';
        $dir = trim($dir, '/');
        $path = $dir ? $this->baseMediaPath . $dir : $this->baseMediaPath;

        $name = $this->request->getPost('name');
        $targetPath = $path . '/' . $name;

        if (is_dir($targetPath)) {
            $this->deleteDirectory($targetPath);
            return $this->response->setJSON(['status' => 'success', 'message' => 'Directory deleted successfully']);
        } elseif (is_file($targetPath)) {
            unlink($targetPath);
            return $this->response->setJSON(['status' => 'success', 'message' => 'File deleted successfully']);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Delete failed']);
    }

    private function deleteDirectory($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }
}
