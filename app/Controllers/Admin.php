<?php

namespace App\Controllers;

class Admin extends BaseController
{
    public function index()
    {
        return redirect()->to(site_url('admin/dashboard')); 
    }
}