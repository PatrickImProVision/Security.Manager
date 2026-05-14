<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->group('install', static function (RouteCollection $routes): void {
    $routes->get('/', 'Install::index');
    $routes->get('new', 'Install::fresh');
    $routes->get('restore', 'Install::restore');
    $routes->post('backup/delete', 'Install::deleteBackup');
    $routes->post('database', 'Install::saveDatabase');
    $routes->post('test-connection', 'Install::testConnection');
    $routes->get('schema', 'Install::schema');
    $routes->post('schema', 'Install::runSchema');
    $routes->get('restore/schema', 'Install::schema');
    $routes->post('restore/schema', 'Install::runSchema');
    $routes->get('admin', 'Install::admin');
    $routes->post('admin', 'Install::saveAdmin');
    $routes->get('complete', 'Install::complete');
    $routes->post('finish', 'Install::finish');
    $routes->get('uninstall', 'Install::uninstall');
    $routes->post('uninstall/confirm', 'Install::uninstallConfirm');
    $routes->get('uninstall/next', 'Install::uninstallNext');
});

$routes->group('Member', ['namespace' => 'App\Controllers\Member'], static function (RouteCollection $routes): void {
    $routes->get('List', 'User::memberList');
    $routes->get('List/Search', 'User::searchMemberList');
});

$routes->group('Member/User', ['namespace' => 'App\Controllers\Member'], static function (RouteCollection $routes): void {
    $routes->get('MyProfile', 'User::profile');
    $routes->get('Profile/(:num)', 'User::viewUser/$1');
    $routes->get('Create', 'User::newUser');
    $routes->post('Create', 'User::createUser');
    $routes->post('Delete/(:num)', 'User::deleteUser/$1');
    $routes->get('Register', 'User::register');
    $routes->post('Register', 'User::create');
    $routes->get('Login', 'User::login');
    $routes->post('Login', 'User::authenticate');
    $routes->get('ForgotPassword', 'User::forgotPassword');
    $routes->post('ForgotPassword', 'User::sendForgotPassword');
    $routes->get('Activate/(:segment)', 'User::activate/$1');
    $routes->get('Edit/(:num)', 'User::edit/$1');
    $routes->post('Edit/(:num)', 'User::update/$1');
    $routes->get('Roles', 'User::roles');
    $routes->get('AssignRole', 'User::assignRole');
    $routes->get('AssignRole/Search', 'User::searchUsers');
    $routes->post('AssignRole', 'User::saveAssignedRole');
    $routes->get('Roles/New', 'User::newRole');
    $routes->post('Roles/New', 'User::createRole');
    $routes->get('Roles/View/(:num)', 'User::viewRole/$1');
    $routes->get('Roles/Edit/(:num)', 'User::editRole/$1');
    $routes->post('Roles/Edit/(:num)', 'User::updateRole/$1');
    $routes->post('Roles/Delete/(:num)', 'User::deleteRole/$1');
    $routes->get('DeActivate/(:segment)', 'User::deactivate/$1');
    $routes->post('Logout', 'User::logout');
});

$routes->group('Content', ['namespace' => 'App\Controllers\Content'], static function (RouteCollection $routes): void {
    $routes->get('Public', static fn () => redirect()->to(site_url('Content/Public/Index')));
    $routes->get('Public/Index', 'PublicContent::index');
    $routes->get('Public/Create', 'PublicContent::create');
    $routes->post('Public/Create', 'PublicContent::store');
    $routes->get('Public/View/(:num)', 'PublicContent::view/$1');
    $routes->get('Public/View/(:segment)', 'PublicContent::viewSlug/$1');
    $routes->get('Public/Edit/(:num)', 'PublicContent::edit/$1');
    $routes->post('Public/Edit/(:num)', 'PublicContent::update/$1');
    $routes->get('Public/Delete/(:num)', 'PublicContent::confirmDelete/$1');
    $routes->post('Public/Delete/(:num)', 'PublicContent::delete/$1');
    $routes->get('Public/(:segment)', 'PublicContent::viewSlug/$1');
    $routes->get('Community', static fn () => redirect()->to(site_url('Content/Community/Index')));
    $routes->get('Community/Index', 'CommunityContent::index');
    $routes->get('Community/Create', 'CommunityContent::create');
    $routes->post('Community/Create', 'CommunityContent::store');
    $routes->get('Community/View/(:num)', 'CommunityContent::view/$1');
    $routes->get('Community/Edit/(:num)', 'CommunityContent::edit/$1');
    $routes->post('Community/Edit/(:num)', 'CommunityContent::update/$1');
    $routes->get('Community/Delete/(:num)', 'CommunityContent::confirmDelete/$1');
    $routes->post('Community/Delete/(:num)', 'CommunityContent::delete/$1');
    $routes->get('Community/Categories', static fn () => redirect()->to(site_url('Content/Community/Categories/Index')));
    $routes->get('Community/Categories/Index', 'CommunityContent::categories');
    $routes->post('Community/Categories/Save', 'CommunityContent::saveCategory');
    $routes->post('Community/Categories/Delete/(:num)', 'CommunityContent::deleteCategory/$1');
    $routes->get('Personal', static fn () => redirect()->to(site_url('Content/Personal/Index')));
    $routes->get('Personal/Index', 'PersonalContent::index');
    $routes->get('Personal/Inbox', 'PersonalContent::inbox');
    $routes->get('Personal/Sent', 'PersonalContent::sent');
    $routes->get('Personal/Create', 'PersonalContent::create');
    $routes->post('Personal/Create', 'PersonalContent::store');
    $routes->get('Personal/View/(:num)', 'PersonalContent::view/$1');
    $routes->get('Personal/Edit/(:num)', 'PersonalContent::edit/$1');
    $routes->post('Personal/Edit/(:num)', 'PersonalContent::update/$1');
    $routes->get('Personal/Delete/(:num)', 'PersonalContent::confirmDelete/$1');
    $routes->post('Personal/Delete/(:num)', 'PersonalContent::delete/$1');
});

$routes->get('DashBoard', 'DashBoard::index');
$routes->get('DashBoard/Index', 'DashBoard::index');
$routes->get('DashBoard/ModuleManager', static fn () => redirect()->to(site_url('DashBoard/ModuleManager/Index')));
$routes->get('DashBoard/ModuleManager/Index', 'DashBoard::moduleManager');
$routes->post('DashBoard/ContentModules', 'DashBoard::saveContentModules');
$routes->get('DashBoard/WebSettings', static fn () => redirect()->to(site_url('DashBoard/WebSettings/Index')));
$routes->get('DashBoard/WebSettings/Index', 'DashBoard::webSettings');
$routes->post('DashBoard/WebSettings', 'DashBoard::saveWebSettings');
$routes->get('DashBoard/SecurityManager', static fn () => redirect()->to(site_url('DashBoard/SecurityManager/Index')));
$routes->get('DashBoard/SecurityManager/Index', 'DashBoard::securityManager');
$routes->get('DashBoard/SecurityManager/CANG', static fn () => redirect()->to(site_url('DashBoard/SecurityManager/CANG/Index')));
$routes->get('DashBoard/SecurityManager/CANG/Index', 'DashBoard::cangProfiles');
$routes->get('DashBoard/SecurityManager/CANG/Edit/(:num)', 'DashBoard::editCangProfile/$1');
$routes->post('DashBoard/SecurityManager/CANG/Edit/(:num)', 'DashBoard::saveCangProfile/$1');

$routes->get('/', 'Home::index');
