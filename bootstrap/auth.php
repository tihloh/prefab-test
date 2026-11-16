<?php
declare(strict_types=1);

use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Services\UserManager;

function app_auth(): array
{
    static $services = null;
    if ($services !== null) {
        return $services;
    }
    $users = new UserManager([
        'database' => app_database(),
        'map' => new UserMap(
            table: 'users',
            attributes: ['password' => 'password'],
            allowDelete: false,
        ),
    ]);
    $users->prefabConfigure();
    return $services = [$users, new AuthManager()];
}
