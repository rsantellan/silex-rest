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

$app->post('/api/month-amount', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $year = null;
    $month = null;
    $vars = json_decode($request->getContent(), true);
    if(!empty($vars['year'])){
        $year = $vars['year'];
    }
    if(!empty($vars['month'])){
        $month = $vars['month'];
    }

    $returnData = [];
    if(empty($year) || empty($month)){
        $response = [
                    'success' => false,
                ];
        $returnData = ['message' => 'Bad params'];
    } else {
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
        
        if($clientId){
            $url = 'http://contable3.local:9450/app_dev.php/localhost/%s/%s/%s/payments';
            $string = file_get_contents(sprintf($url, $clientId, $month, $year));
            $returnData = json_decode($string);
        }
    }
    return $app->json($returnData, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
->bind('month-amount')
;

$app->post('/api/current-account-data', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    //var_dump($token);
    //$folder, $year, $month
    $year = null;
    $month = null;
    $folder = null;
    $vars = json_decode($request->getContent(), true);
    if(!empty($vars['year'])){
        $year = $vars['year'];
    }
    if(!empty($vars['month'])){
        $month = $vars['month'];
    }
    if(!empty($vars['folder'])){
        $folder = $vars['folder'];
    }
    $returnData = [];
    if(empty($year) || empty($month)  || empty($folder)){
        $response = [
                    'success' => false,
                ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $response = [
                    'success' => true,
                    'username' => $token->getUsername(),
                    'clients' => $app['users']->loadClientByUsername($token->getUsername()),
                    //'username' => $token->getUser()->getId(),
                    //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
                ];
        $found = false;
        if(count($response['clients']) > 0){
            foreach($response['clients'] as $client){
                if($client['folder_number'] == $folder){
                    $found = true;
                }
            }
        }
        $returnData = [];
        if($found){
            $url = 'http://contable3.local:9450/localhost/%s/%s/%s/ccte';
            $string = file_get_contents(sprintf($url, $folder, $month, $year));
            $returnData = json_decode($string);
        }else{
            $response['success'] = false;
        }
    }
    return $app->json($returnData, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
->bind('current-account-data')
;

$app->get('/api/news', function(Request $request) use ($app){
    $token = $app['security.token_storage']->getToken();
    $response = [
                'success' => true,
                'username' => $token->getUsername(),
            ];

    $returnData = [
        'sucess' => true,
        'news' => $app['news']->retrieveLastNews(),
    ];
    return $app->json($returnData, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
->bind('news')
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
