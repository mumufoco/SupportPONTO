<?php

/*
 * --------------------------------------------------------------------
 * Chat and Collaboration
 * --------------------------------------------------------------------
 */
$routes->group('chat', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'ChatController::index', ['as' => 'chat']);
    $routes->get('room/(:num)', 'ChatController::room/$1', ['as' => 'chat.room']);
    $routes->get('room/(:num)/settings', 'ChatController::roomSettings/$1', ['as' => 'chat.room.settings']);
    $routes->get('new/(:num)', 'ChatController::newChat/$1', ['as' => 'chat.new']);
    $routes->get('group/create', 'ChatController::createGroup', ['as' => 'chat.group.create']);
    $routes->post('group/store', 'ChatController::storeGroup', ['as' => 'chat.group.store']);
    $routes->post('room/(:num)/add-member', 'ChatController::addMember/$1', ['as' => 'chat.room.add-member']);
    $routes->post('room/(:num)/remove-member', 'ChatController::removeMember/$1', ['as' => 'chat.room.remove-member']);
    $routes->get('room/(:num)/search', 'ChatController::search/$1', ['as' => 'chat.room.search']);
    $routes->post('upload', 'ChatController::uploadFile', ['as' => 'chat.upload']);
    $routes->get('file/download', 'ChatController::downloadFile', ['as' => 'chat.file.download']);
    $routes->get('push/vapid-key', 'ChatController::getVapidKey', ['as' => 'chat.push.vapid-key']);
    $routes->post('push/subscribe', 'ChatController::subscribePush', ['as' => 'chat.push.subscribe']);
    $routes->post('push/unsubscribe', 'ChatController::unsubscribePush', ['as' => 'chat.push.unsubscribe']);
    $routes->post('push/test', 'ChatController::testPush', ['as' => 'chat.push.test']);
});
