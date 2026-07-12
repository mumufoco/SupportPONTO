<?php

namespace App\Controllers\User;

/**
 * Legacy compatibility wrapper.
 *
 * Canonical profile flow lives in \App\Controllers\ProfileController.
 * Do not add business logic here. Route aliases may continue to target this
 * wrapper, but all functional changes must happen in the canonical controller.
 */
class ProfileController extends \App\Controllers\ProfileController
{
}
