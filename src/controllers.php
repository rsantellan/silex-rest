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
    }else{
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
            $returnData = $app['contableData']->returnPayments($clientId, $month, $year);
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
            $returnData = $app['contableData']->returnCcte($folder, $month, $year);
            $response['success'] = true;
	    if(isset($response['isvalid']))
	    	unset($response['isvalid']);
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
            $app['users']->saveLoadedUsername($user->getUsername());
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
    return sprintf('%s -> %s', $e->getMessage(), $e->getTraceAsString());
});

$app->get('/send-data/{password}', function($password) use($app){
    $response = [
            'success' => false,
            'error' => 'Error',
    ];
    if($password === PASSWORD_PUSH_CODE){
        $response['success'] = true;
        $response['error'] = '';
        $loggedUsers = $app['users']->getAllLoggedUsernames();
        $users = [];
        foreach($loggedUsers as $user){
            $username = $user['username'];
            $email = $user['email'];
            $clients = $app['users']->loadClientByUsername($email);
            $year = (int) date('Y');
            $month = (int) date('n');
            /*
            // Asumo CCTE
            
            $folders = [];
            if(count($clients) > 0){
                foreach($clients as $client){
                    if($client['folder_number'] == $folder){
                        $folders[] = $folder;
                    }
                }
            }
            
            $returnList = [];
            $sendMessage = false;
            foreach($folders as $folder)
            {
                $oldFilesName = SAVE_DATA_FILES.'/'.sprintf('%s-%s-%s', $clientId,$month,$year);
                $returnData = md5(serialize($app['contableData']->returnCcte($folder, $month, $year)));
                $oldHash = file_get_contents($oldFilesName);
                // Compare to a saved file.
                if($oldHash === $returData){
                    // do nothing
                }else{
                    // Send message
                    $sendMessage = true;
                    $users[$username] = $username;
                    // Save again to file
                    file_put_contents($oldFilesName, $returData);
                }
            }
            */
            // Asumo Payments
            $clientId = null;
            if(count($clients) > 0){
                $first = array_pop($clients);
                $clientId = $first['id'];
            }
            if($clientId){
                $oldFilesName = SAVE_DATA_FILES.'/'.sprintf('%s-%s-%s', $clientId,$month,$year);
                $returnData = md5(serialize($app['contableData']->returnPayments($clientId, $month, $year)));
		$oldHash = null;
		if(file_exists($oldFilesName))
		{
			$oldHash = file_get_contents($oldFilesName);
		}
                // Compare to a saved file.
                if($oldHash === $returnData){
                    // do nothing
                }else{
                    // Send message
                    $sendMessage = true;
                    $users[$username] = $username;
                    // Save again to file
                    //file_put_contents($oldFilesName, $returnData);
                }
            }            

        }
	if(count($users) > 0)
	{
	        $title = 'This is a title';
        	$body = 'This is a body';
	        $returnData = $app['pushapi']->doPush($title, $body, $users);
		file_put_contents('/tmp/aux.log', $returnData);
	}
    }
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});
