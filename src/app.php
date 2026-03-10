<?php

use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\HttpFragmentServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = new Application();
$app->register(new ServiceControllerServiceProvider());
$app->register(new HttpFragmentServiceProvider());

$app->register(new Silex\Provider\SecurityServiceProvider());

$app['security.jwt'] = [
    'secret_key' => JWT_SECRET_KEY.'1',
    'life_time'  => 31536000,
    'options'    => [
        'username_claim' => 'name', // default name, option specifying claim containing username
        'header_name' => 'Authorization', // default null, option for usage normal oauth2 header
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

$app['clientData'] = function () use ($app){
    return new \Maith\Data\ClientData(URL_CONTABLE_BASE_URL, CONTABLE_TOKEN);
};

$app['security.default_encoder'] = function ($app) {
    // Plain text (e.g. for debugging)
    return new \Maith\Security\Md5PasswordEncoder();
};

$app['users'] = function () use ($app) {
	return new \Maith\Security\UserProvider($app['dbs']['mysql_read'], $app['clientData']);
};

$app['news'] = function () use ($app){
    return new \Maith\Data\NewsProvider($app['dbs']['mysql_write']);
};

$app['pushapi'] = function () use ($app){
    return new \Maith\Push\Api($app['dbs']['mysql_read'],HOST_PUSH_CODE, URL_PUSH_CODE, KEY_PUSH_CODE);
};

$app['contableData'] = function () use ($app){
    return new \Maith\Data\ContableData($app['dbs']['mysql_write'], URL_CONTABLE_BASE_URL, CONTABLE_TOKEN, URL_CONTABLE_PAYMENT, URL_CONTABLE_CCTE, URL_CONTABLE_ACCOUNT_PER_CLIENT, URL_CONTABLE_PAYMENT_MULTIPLE, URL_CONTABLE_CLIENT_RETRIEVE_EXPIRATIONS, URL_CONTABLE_RETRIEVE_PUBLIC_TASKS);
};


$app['security.firewalls'] = array(
    'login' => [
        'pattern' => 'login|register|oauth|send-data|send-user-data|get-folder-data|contact|get-file',
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

$app->before(function (Request $request) {
    if ($request->getMethod() === 'OPTIONS') {
        $response = new Response();
        $response->setStatusCode(204);

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition, Content-Length');

        return $response;
    }
}, Silex\Application::EARLY_EVENT);

$app->after(function (Request $request, Response $response) {
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    $response->headers->set('Access-Control-Expose-Headers', 'Content-Disposition, Content-Length');
});
return $app;
