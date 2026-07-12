<?php

namespace App\Controllers\Admin;

/**
 * Legacy compatibility wrapper.
 *
 * Canonical settings orchestration lives in \App\Controllers\SettingsController.
 * Keep this class thin and do not add business logic here.
 */
class SettingsController extends \App\Controllers\SettingsController
{
    public function index()
    {
        return parent::index();
    }
}
