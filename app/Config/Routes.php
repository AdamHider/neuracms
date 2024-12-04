<?php

use App\Controllers\Pages;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
//$routes->get('/', 'Home::index');

$routes->group('pages', [], function($routes) {
    $routes->get('(:segment)', [Pages::class, 'view']);
});

$routes->group('auth', [], function($routes) {
    $routes->get('login', 'Auth::login');
    $routes->post('authenticate', 'Auth::authenticate');
    $routes->get('logout', 'Auth::logout');
    $routes->get('register', 'Auth::register');
    $routes->post('store', 'Auth::store');
});

$routes->group('admin', ['filter' => 'auth'], function($routes) {
    $routes->get('', 'Admin::index');
    $routes->get('dashboard', 'Admin\Dashboard::index');
    $routes->group('pages', [], function($routes) {
        $routes->get('', 'Admin\Page::index');
        $routes->get('create', 'Admin\Page::create');
        $routes->post('store', 'Admin\Page::store');
        $routes->get('edit/(:num)', 'Admin\Page::edit/$1');
        $routes->post('update/(:num)', 'Admin\Page::update/$1');
        $routes->get('delete/(:num)', 'Admin\Page::delete/$1');
    });
});