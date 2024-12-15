<?php

use App\Controllers\Pages;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
//$routes->get('/', 'Home::index');

/*
$routes->group('pages', [], function($routes) {
    $routes->get('(:segment)', [Pages::class, 'view']);
});
*/
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
        $routes->get('form/(:num)', 'Admin\Page::form/$1');
        $routes->get('form', 'Admin\Page::form');
        $routes->post('store', 'Admin\Page::store');
        $routes->post('update/(:num)', 'Admin\Page::update/$1');
        $routes->get('delete/(:num)', 'Admin\Page::delete/$1');
    });
    $routes->group('menus', function($routes) {
        $routes->get('', 'Admin\Menu::index');
        $routes->get('create', 'Admin\Menu::create');
        $routes->post('store', 'Admin\Menu::store');
        $routes->get('edit/(:num)', 'Admin\Menu::edit/$1');
        $routes->post('update/(:num)', 'Admin\Menu::update/$1');
        $routes->get('delete/(:num)', 'Admin\Menu::delete/$1');
    });
    $routes->group('media', function($routes) {
        $routes->get('', 'Admin\Media::index');
        $routes->get('list', 'Admin\Media::listFiles');
        $routes->post('upload', 'Admin\Media::upload');
        $routes->post('create-directory', 'Admin\Media::createDirectory');
        $routes->post('rename', 'Admin\Media::rename');
        $routes->post('delete', 'Admin\Media::delete');
    });
    $routes->group('languages', function($routes) {
        $routes->get('', 'Admin\Language::index');
        $routes->get('create', 'Admin\Language::create');
        $routes->post('store', 'Admin\Language::store');
        $routes->get('edit/(:num)', 'Admin\Language::edit/$1');
        $routes->post('update/(:num)', 'Admin\Language::update/$1');
        $routes->get('delete/(:num)', 'Admin\Language::delete/$1');
    });
});

$routes->get('image/(:any)', 'Image::index/$1');


$routes->get('component/getComponent/(:segment)', 'Component::getComponent/$1');
$routes->post('component/getGeneratedContent', 'Component::getGeneratedContent');

//$routes->addPlaceholder('alias', '[a-zA-Z0-9-_]+');
$routes->get('(:any)', 'Page::view/$1');
