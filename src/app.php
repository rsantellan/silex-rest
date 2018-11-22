<?php

use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\HttpFragmentServiceProvider;

$app = new Application();
$app->register(new ServiceControllerServiceProvider());
$app->register(new HttpFragmentServiceProvider());

$app->register(new Silex\Provider\SecurityServiceProvider());

$app['security.jwt'] = [
    'secret_key' => 'Very_secret_key',
    'life_time'  => 86400,
    'options'    => [
        'username_claim' => 'name', // default name, option specifying claim containing username
        'header_name' => 'X-Access-Token', // default null, option for usage normal oauth2 header
        'token_prefix' => 'Bearer',
    ]
];
$app->register(new Silex\Provider\SecurityJWTServiceProvider());

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'dbs.options' => array (
        'mysql_read' => array(
            'driver'    => 'pdo_mysql',
            'host'      => DB_HOST,
            'dbname'    => DB_SCHEMA,
            'user'      => DB_USERNAME,
            'password'  => DB_PASSWORD,
            'charset'   => 'utf8mb4',
        ),
        'mysql_write' => array(
            'driver'    => 'pdo_mysql',
            'host'      => DB_CLIENT_HOST,
            'dbname'    => DB_CLIENT_SCHEMA,
            'user'      => DB_CLIENT_USERNAME,
            'password'  => DB_CLIENT_PASSWORD,
            'charset'   => 'utf8mb4',
        ),
    ),
));

$app['security.default_encoder'] = function ($app) {
    // Plain text (e.g. for debugging)
    return new \Maith\Security\Md5PasswordEncoder();
};

$app['users'] = function () use ($app) {
	return new \Maith\Security\UserProvider($app['dbs']['mysql_read'], $app['dbs']['mysql_write']);
	/*
    $users = [
        'admin' => array(
            'roles' => array('ROLE_ADMIN'),
            // raw password is foo
            'password' => '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg==',
            'enabled' => true
        ),
    ];

    return new \Symfony\Component\Security\Core\User\InMemoryUserProvider($users);
    */
};

$app['news'] = function () use ($app){
    return new \Maith\Data\NewsProvider($app['dbs']['mysql_write']);
};

$app['security.firewalls'] = array(
    'login' => [
        'pattern' => 'login|register|oauth',
        'anonymous' => true,
    ],
    'secured' => array(
        'pattern' => '^.*$',
        'logout' => array('logout_path' => '/logout'),
        'users' => $app['users'],
        'jwt' => array(
            'use_forward' => true,
            'require_previous_session' => false,
            'stateless' => true,
        )
    ),
);


return $app;
