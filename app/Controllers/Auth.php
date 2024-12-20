<?php 

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    public $data;
    public function __construct()
    {
        helper(['form']);
    }

    public function login()
    {

        $this->data['settings'] = [
            'layout' => 'default',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Dashboard',
            'path' => '/admin/dashboard'
        ];
        return view('auth/login', $this->data);
    }
    public function authenticate()
    {
        $session = session();
        $model = new UserModel();
        
        $username = $this->request->getVar('username');
        $password = $this->request->getVar('password');
        
        $data = $model->where('username', $username)->first();
        if($data){
            $pass = $data['password'];
            $authenticatePassword = password_verify($password, $pass);
            if($authenticatePassword){
                $ses_data = [
                    'id'       => $data['id'],
                    'username' => $data['username'],
                    'email'    => $data['email'],
                    'logged_in' => TRUE
                ];
                $session->set($ses_data);
                return redirect()->to('/admin/dashboard');
            }else{
                $session->set('username', $data['username']);
                $session->setFlashdata('msg', 'Wrong Password');
                return redirect()->to('/auth/login');
            }
        }else{
            $session->setFlashdata('msg', 'Username not found');
            return redirect()->to('/auth/login');
        }
    }

    public function logout()
    {
        $session = session();
        $session->destroy();
        return redirect()->to('/auth/login');
    }

    public function register()
    {
        $this->data['settings'] = [
            'layout' => 'default',
            'menu' => [
                'id' => 2
            ],
            'title' => 'Register',
            'path' => '/auth/register'
        ];

        return view('auth/register', $this->data);
    }

    public function store()
    {
        $session = session();
        $model = new UserModel();

    
        if (!$this->validate($model->validationRules)) {
            $this->data['settings'] = [
                'layout' => 'default',
                'menu' => [
                    'id' => 2
                ],
                'title' => 'Register',
                'path' => '/auth/register'
            ];
            $this->data['validation'] =  $this->validator;
            return view('auth/register', $this->data);
        }
    
        $data = [
            'username' => $this->request->getVar('username'),
            'email'    => $this->request->getVar('email'),
            'password' => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT),
        ];
    
        $model->save($data);
    
        return redirect()->to('/auth/login');
    }

    
}