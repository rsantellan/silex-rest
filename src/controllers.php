<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/', function () use ($app) {
    $token = $app['security.token_storage']->getToken();
    $response = [
                'success' => true,
                'username' => $token->getUsername(),
                //'username' => $token->getUser()->getId(),
                //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
            ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
->bind('homepage')
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
        var_dump($user);die;
        if (! $app['security.encoder.digest']->isPasswordValid($user->getPassword(), $vars['_password'], '')) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $vars['_username']));
        } else {
            $response = [
                'success' => true,
                'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
            ];
        }
    } catch (UsernameNotFoundException $e) {
        $response = [
            'success' => false,
            'error' => 'Invalid credentials',
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
    return "error";
});
