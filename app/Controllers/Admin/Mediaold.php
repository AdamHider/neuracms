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
            'title' => 'File Explorer',
            'path' => '/admin/file-explorer'
        ];
        return view('admin/file_explorer/index', $data);
    }

    public function getFileList($currentDir = '')
    {
        $currentDir = trim($currentDir, '/');
        $currentPath = $currentDir ? $this->baseMediaPath . $currentDir : $this->baseMediaPath;

        $data = [
            'currentDir' => $currentDir,
            'files' => directory_map($currentPath, 1)
        ];

        return view('admin/media/file_list', $data);
    }

    public function upload($currentDir = '')
    {
        $currentDir = trim($currentDir, '/');
        $currentPath = $currentDir ? $this->baseMediaPath . $currentDir : $this->baseMediaPath;

        $validationRule = [
            'file' => [
                'label' => 'Media File',
                'rules' => 'uploaded[file]|mime_in[file,image/jpg,image/jpeg,image/png,video/mp4,video/avi]|max_size[file,20480]',
            ],
        ];

        if (!$this->validate($validationRule)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $file = $this->request->getFile('file');
        $file->move($currentPath);

        return redirect()->back()->withInput()->with('success', 'File uploaded successfully.');
    }

    public function createDirectory($currentDir = '')
    {
        $currentDir = trim($currentDir, '/');
        $currentPath = $currentDir ? $this->baseMediaPath . $currentDir : $this->baseMediaPath;
        
        $dirName = $this->request->getPost('dirname');
        $newDirPath = $currentPath . '/' . $dirName;

        if (!is_dir($newDirPath)) {
            mkdir($newDirPath, 0777, true);
        }

        return redirect()->to("/admin/media/$currentDir")->with('success', 'Directory created successfully.');
    }

    public function deleteFile($currentDir, $fileName)
    {
        $currentDir = trim($currentDir, '/');
        $filePath = $this->baseMediaPath . $currentDir . '/' . $fileName;

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        return $this->index($currentDir);
    }

    public function deleteDirectory($currentDir, $dirName)
    {
        $currentDir = trim($currentDir, '/');
        $dirPath = $this->baseMediaPath . $currentDir . '/' . $dirName;

        if (is_dir($dirPath)) {
            rmdir($dirPath);
        }

        return $this->index($currentDir);
    }

    public function renameFile($currentDir = '')
    {
        $currentDir = trim($currentDir, '/');
        $currentPath = $currentDir ? $this->baseMediaPath . $currentDir : $this->baseMediaPath;
        
        $oldName = $this->request->getPost('oldName');
        $newName = $this->request->getPost('newName');
        
        if (file_exists($currentPath . '/' . $oldName)) {
            rename($currentPath . '/' . $oldName, $currentPath . '/' . $newName);
        }

        return redirect()->to("/admin/media/$currentDir")->with('success', 'File renamed successfully.');
    }

    public function renameDirectory($currentDir = '')
    {
        $currentDir = trim($currentDir, '/');
        $currentPath = $currentDir ? $this->baseMediaPath . $currentDir : $this->baseMediaPath;
        
        $oldName = $this->request->getPost('oldName');
        $newName = $this->request->getPost('newName');
        
        if (is_dir($currentPath . '/' . $oldName)) {
            rename($currentPath . '/' . $oldName, $currentPath . '/' . $newName);
        }

        return redirect()->to("/admin/media/$currentDir")->with('success', 'Directory renamed successfully.');
    }
}
