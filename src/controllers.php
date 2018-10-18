<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/api/', function () use ($app) {
    $token = $app['security.token_storage']->getToken();
    //var_dump($token);
    $response = [
                'success' => true,
                'username' => $token->getUsername(),
                'clients' => $app['users']->loadClientByUsername($token->getUsername()),
                //'username' => $token->getUser()->getId(),
                //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
            ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
->bind('homepage')
;

$app->get('/api/month-amount/{year}/{month}', function ($year, $month) use ($app) {
    $token = $app['security.token_storage']->getToken();
    //var_dump($token);
    $response = [
                'success' => true,
                'username' => $token->getUsername(),
                'clients' => $app['users']->loadClientByUsername($token->getUsername()),
                //'username' => $token->getUser()->getId(),
                //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
            ];
    $clientId = null;
    if(count($response['clients']) > 0){
        $first = array_pop($response['clients']);
        $clientId = $first['id'];
    }
    $returnData = [];
    if($clientId){
        $string = file_get_contents(sprintf('http://contable3.local:9450/app_dev.php/localhost/%s/%s/%s/payments', $clientId, $month, $year));
        $returnData = json_decode($string);
    }
    
    return $app->json($returnData, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
->bind('month-amount')
;

$app->post('/api/login', function(Request $request) use ($app){
    $vars = json_decode($request->getContent(), true);
    try {
        if (empty($vars['_username']) || empty($vars['_password'])) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $vars['_username']));
        }
        /**
         * @var $user User
         */
        $user = $app['users']->loadUserByUsername($vars['_username']);
        if (! $app['security.default_encoder']->isPasswordValid($user->getPassword(), $vars['_password'], '')) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist 2.', $vars['_username']));
        } else {
            $response = [
                'success' => true,
                'error' => '',
                'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
            ];
        }
    } catch (UsernameNotFoundException $e) {
        $response = [
            'success' => false,
            'error' => 'Invalid credentials',
            'token' => '',
            'aux' => $e->getMessage(),
        ];
    } catch (\Exception $e){
        $response = [
            'success' => false,
            'error' => 'Invalid credentials',
            'token' => '',
            'aux' => $e->getMessage(),
        ];
    }

    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->get('/api/protected_resource', function() use ($app){
    return $app->json(['hello' => 'world']);
});

$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }
    return $e->getTraceAsString();
});
