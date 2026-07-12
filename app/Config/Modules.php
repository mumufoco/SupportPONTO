<?php

namespace Config;

use CodeIgniter\Modules\Modules as BaseModules;

class Modules extends BaseModules
{
    /**
     * --------------------------------------------------------------------------
     * Enable Auto-Discovery?
     * --------------------------------------------------------------------------
     * If true, then auto-discovery will happen across all elements listed in
     * $activeExplorers below. If false, no auto-discovery will happen at all,
     * giving a slight performance boost.
     *
     * @var bool
     */
    public $enabled = true;

    /**
     * --------------------------------------------------------------------------
     * Enable Auto-Discovery Within Composer Packages?
     * --------------------------------------------------------------------------
     * If true, then auto-discovery will happen across all namespaces loaded
     * by Composer, as well as the namespaces configured locally.
     *
     * @var bool
     */
    public $discoverInComposer = true;

    /**
     * --------------------------------------------------------------------------
     * Auto-Discovery Rules
     * --------------------------------------------------------------------------
     * Lists the aliases of all discovery classes that will be active
     * and used during the current application request.
     *
     * If it is not listed, only the base application elements will be used.
     *
     * @var string[]
     */
    public $activeExplorers = [
        'events',
        'registrars',
        'routes',
        'services',
    ];

    /**
     * --------------------------------------------------------------------------
     * Composer Packages
     * --------------------------------------------------------------------------
     * Lists the composer packages that contain Codeigniter support.
     *
     * @var string[]
     */
    public $composerPackages = [];
}
