<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;

//Request::setTrustedProxies(array('127.0.0.1'));

$app->get('/api/', function () use ($app) {
    $token = $app['security.token_storage']->getToken();
    $fullData = $app['users']->loadClientByUsername($token->getUsername(), true); // Check
    $response = [
        'success' => true,
        'username' => $token->getUsername(),
        'clients' => $fullData['clients'],
        'permissions'=> $fullData['global'],
    ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
    ->bind('homepage');

$app->post('/api/month-amount', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $year = null;
    $month = null;
    $vars = json_decode($request->getContent(), true);
    if (!empty($vars['year'])) {
        $year = $vars['year'];
    }
    if (!empty($vars['month'])) {
        $month = $vars['month'];
    }

    $returnData = [];
    if (empty($year) || empty($month)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $response = [
            'success' => true,
            'username' => $token->getUsername(),
            'clients' => $app['users']->loadClientByUsername($token->getUsername()), // CHECK
            //'username' => $token->getUser()->getId(),
            //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
        ];
        $sendClients = [];
        foreach($response['clients'] as $client) {
            if ($client['permissions']['month-amount']) {
                $sendClients[] = $client['id'];
            }
        }
        if (count($sendClients) > 0) {
            $returnData = $app['contableData']->returnPaymentsByClients($sendClients, $month, $year);
        }
    }
    return $app->json($returnData, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
    ->bind('month-amount');

$app->post('/api/current-account-data', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    //$folder, $year, $month
    $year = null;
    $month = null;
    $folder = null;
    $vars = json_decode($request->getContent(), true);
    if (!empty($vars['year'])) {
        $year = $vars['year'];
    }
    if (!empty($vars['month'])) {
        $month = $vars['month'];
    }
    if (!empty($vars['folder'])) {
        $folder = $vars['folder'];
    }
    $returnData = [];
    $forbidden = false;
    if (empty($year) || empty($month) || empty($folder)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $response = [
            'success' => true,
            'username' => $token->getUsername(),
            'clients' => $app['users']->loadClientByUsername($token->getUsername()), //CHECK
            //'username' => $token->getUser()->getId(),
            //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
        ];
        $found = false;
        foreach ($response['clients'] as $client) {
            if ($client['folder_number'] == $folder) {
                if ($client['permissions']['current-account-data']) {
                    $found = true;
                    break;
                }
            }
        }
        if ($found) {
            $returnData = $app['contableData']->returnCcte($folder, $month, $year);
            $response['success'] = true;
            if (isset($response['isvalid']))
                unset($response['isvalid']);
        } else {
            $response['success'] = false;
        }
    }
    $responseCode = null;
    if ($forbidden) {
        $responseCode = Response::HTTP_FORBIDDEN;
    } else {
        if ($response['success'] == true) {
            $responseCode = Response::HTTP_OK;
        }
    }
    if (empty($responseCode)) {
        $responseCode = Response::HTTP_BAD_REQUEST;
    }
    return $app->json($returnData, $responseCode);
})->bind('current-account-data');

$app->post('/api/account-data-date-range', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    //$clients, $year, $to
    $from = null;
    $to = null;
    $clients = null;
    $vars = json_decode($request->getContent(), true);
    if (!empty($vars['from'])) {
        $from = $vars['from'];
    }
    if (!empty($vars['to'])) {
        $to = $vars['to'];
    }
    if (!empty($vars['clients'])) {
        $clients = $vars['clients'];
    }
    $returnData = [];
    $forbidden = false;
    if (empty($from) || empty($to) || empty($clients) || !is_array($clients)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $response = [
            'success' => true,
            'clients' => $app['users']->loadClientByUsername($token->getUsername()), // CHECK
        ];
        $sendClients = [];
        foreach($response['clients'] as $client) {
            if ($client['permissions']['month-amount'] && in_array($client['id'], $clients)) {
                $sendClients[] = $client['id'];
            }
        }
        if (!empty($sendClients)) {
            $returnData = $app['contableData']->returnCctePerClientsPerRange($sendClients, $from, $to);
            $response = [
                'success' => true,
                'error' => '',
                'data' => $returnData,
            ];
            if (isset($response['isvalid'])) {
                unset($response['isvalid']);
            }

        } else {
            $response['success'] = false;
        }
    }
    $responseCode = null;
    if ($forbidden) {
        $responseCode = Response::HTTP_FORBIDDEN;
    } else {
        if ($response['success'] == true) {
            $responseCode = Response::HTTP_OK;
        }
    }
    if (empty($responseCode)) {
        $responseCode = Response::HTTP_BAD_REQUEST;
    }
    return $app->json($response, $responseCode);
})->bind('account-data-date-range');


$app->get('/api/news', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $response = [
        'success' => true,
        'username' => $token->getUsername(),
    ];

    $returnData = [
        'success' => true,
        'news' => $app['news']->retrieveLastNews(20),
    ];
    return $app->json($returnData, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})
    ->bind('news');

$app->post('/api/login', function (Request $request) use ($app) {
    $vars = json_decode($request->getContent(), true);
    try {
        if (empty($vars['_username']) || empty($vars['_password'])) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $vars['_username']));
        }

        try {
            $externalLogin = $app['users']->externalLogin($vars['_username'], $vars['_password']); //CHECK
            $user = $externalLogin['user'];
            $userData = $externalLogin['data'];
            $response = [
                'success' => true,
                'error' => '',
                'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
                'user' => [
                    'username' => $userData['username'],
                    'group' => 0,
                    'superuser'  => 0,
                    'firstName'  => $userData['firstName'],
                    'lastName'  => $userData['lastName'],
                    'children' => [],
                ]
            ];
        } catch (\Exception $e) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist or password is incorrect.', $vars['_username']));
        }
    } catch (UsernameNotFoundException $e) {
        $response = [
            'success' => false,
            'error' => 'Invalid credentials',
            'token' => '',
            'aux' => $e->getMessage(),
        ];
    } catch (\Exception $e) {
        $response = [
            'success' => false,
            'error' => 'Invalid credentials',
            'token' => '',
            'aux' => $e->getMessage(),
        ];
    }

    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->get('/get-file/{clientId}/{id}/{hash}', function ($clientId, $id, $hash) use ($app) {
    $today = new \DateTime();
    $testHash = md5($today->format('Y-m-d'));
    if ($hash != $testHash) {
        $app->abort(403);
        return;
    }
    $file = $app['clientData']->getFile($clientId, $id);
    if (!file_exists($file)) {
        $app->abort(404);
        return;
    }
    return $app->sendFile($file);
})->bind('download_file');

$app->get('/files', function () use ($app) {
    $response = [
        'success' => false,
    ];
    try {
        $token = $app['security.token_storage']->getToken();
        $clients = $app['users']->loadClientByUsername($token->getUsername()); // CHECK
        $data = [];
        $today = new \DateTime();
        $hash = md5($today->format('Y-m-d'));
        foreach ($clients as $client)
        {
            $valid = false;
            if ($client['permissions']['files']) {
                $valid = true;
            }
            if ($valid) {
                $files = $app['clientData']->getFiles($client['id']);
                if (!empty($files)) {
                    $returnData = [
                        'name' => $files['name'],
                        'files' => []
                    ];
                    foreach ($files['files'] as $file) {
                        $returnData['files'][] = [
                            'name' => $file['name'],
                            'url' => $app['url_generator']->generate('download_file', array( 'clientId' => $client['id'], 'id' => $file['id'], 'hash' => $hash )),
                        ];
                    }
                    $data[] = $returnData;
                }
            }
        }
        $response['success'] = true;
        $response['business'] = $data;
    } catch (\Exception $e) {
        throw $e;
        $response['message'] = 'Ocurrio un error';
    }
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});


$app->get('/contact_info', function () use ($app) {

    $response = [
        'html' => $app['clientData']->getContactData(),
    ];
    return $app->json($response, Response::HTTP_OK);
});

$app->post('/payment', function(Request $request) use ($app){
    $token = $app['security.token_storage']->getToken();
    $response = [
        'success' => false,
    ];
    $text = isset($_POST['text']) ? $_POST['text'] : '-';
    $amount = isset($_POST['amount']) ? $_POST['amount'] : null;
    if (empty($amount)) {
        $response['message'] = 'Campos invalidos';
    } else {
        $fileName = null;
        $newFileName = null;
        $dest_path = null;
        $error = false;
        if (isset($_FILES['file'])) {
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                // get details of the uploaded file
                $fileTmpPath = $_FILES['file']['tmp_name'];
                $fileName = $_FILES['file']['name'];
                $fileSize = $_FILES['file']['size'];
                $fileType = $_FILES['file']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                $uploadFileDir = '/tmp/';
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if(!move_uploaded_file($fileTmpPath, $dest_path)){
                    $response['message'] = "Error al subir el archivo";
                    $error = true;
                }
            } else {
                $response['message'] = 'Error al subir el archivo';
                $error = true;
            }
        }
        if (!$error) {
            $responseData = $app['clientData']->sendPaymentFile($token->getUsername(), $text, $amount, $fileName, $newFileName, $dest_path);
            $response['success'] = $responseData['isvalid'];
            if (!$response['success']) {
                $response['message'] = $responseData['message'];
            }
        }
    }
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->get('/due_calendar', function () use ($app) {
    $response = [
        'success' => false,
    ];
    try {
        $token = $app['security.token_storage']->getToken();
        $clients = $app['users']->loadClientByUsername($token->getUsername()); // CHECK
        $data = [];
        foreach ($clients as $client)
        {
            if ($client['permissions']['month-amount']) {
                $payments = $app['clientData']->getCalendarPaymentData($client['id']);
                if (!empty($payments)) {
                    $data[] = $payments;
                }
            }
        }
        $response['success'] = true;
        $response['business'] = $data;
    } catch (\Exception $e) {
        $response['message'] = 'Ocurrio un error';
    }
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->post('/contact', function(Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $vars = json_decode($request->getContent(), true);
    $name = null;
    $email = null;
    $phone = null;
    $comment = null;
    if (!empty($vars['name'])) {
        $name = $vars['name'];
    }
    if (!empty($vars['email'])) {
        $email = $vars['email'];
    }
    if (!empty($vars['phone'])) {
        $phone = $vars['phone'];
    }
    if (!empty($vars['comment'])) {
        $comment = $vars['comment'];
    }
    $response = [
        'success' => false,
    ];
    if (empty($name) || empty($email) || empty($phone)) {
        $response['message'] = 'Los campos no pueden venir vacios';
    } else {
        $responseData = $app['clientData']->saveNewContact($name, $email, $phone, $comment);
        $response['success'] = $responseData['isvalid'];
        if (!$response['success']) {
            $response['message'] = $responseData['message'];
        }
    }
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->get('/certificates', function (Request $request) use ($app) {

    $response = [
        'success' => false,
    ];
    $debug = $request->get('debug', false);
    try {
        $token = $app['security.token_storage']->getToken();
        $clients = $app['users']->loadClientByUsername($token->getUsername()); // CHECK
        $data = [];
        foreach ($clients as $client)
        {
            $valid = false;
            if ($client['permissions']['certificates']) {
                $valid = true;
            }
            if ($valid) {
                $certificate = $app['clientData']->getDgiQr($client['id'], $debug);
                if (!empty($certificate)) {
                    $data[] = $certificate;
                }
            }
        }
        $response['success'] = true;
        $response['business'] = $data;
    } catch (\Exception $e) {
        $response['message'] = 'Ocurrio un error';
    }
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->get('/api/protected_resource', function () use ($app) {
    return $app->json(['hello' => 'world']);
});

$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }
    return sprintf('%s -> %s', $e->getMessage(), $e->getTraceAsString());
});

$app->post('/send-user-data', function (Request $request) use ($app) {
    $vars = json_decode($request->getContent(), true);
    $havePassword = isset($vars['password']) && !empty($vars['password']);
    if (isset($_SERVER['HTTP_CLIENT_IP'])
        || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        || !(in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1')) || php_sapi_name() === 'cli-server')
    ) {
        $showError = true;
        if ($havePassword && $vars['password'] === PASSWORD_PUSH_CODE) {
            $showError = false;
        }
        if ($showError) {
            header('HTTP/1.0 403 Forbidden');
            exit('You are not allowed to access here.');
        }
    }
    $response = [
        'success' => false,
        'error' => 'Error',
        'fullData' => null,
    ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->post('/get-folder-data', function (Request $request) use ($app) {
    if (isset($_SERVER['HTTP_CLIENT_IP'])
        || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        || !(in_array(@$_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1')) || php_sapi_name() === 'cli-server')
    ) {
        header('HTTP/1.0 403 Forbidden');
        exit('You are not allowed to access this file. Check ' . basename(__FILE__) . ' for more information.');
    }
    $vars = json_decode($request->getContent(), true);
    $response = [
        'success' => false,
        'error' => '',
    ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->get('/send-data/{password}', function ($password) use ($app) {
    $response = [
        'success' => false,
        'error' => 'Error',
    ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->post('/api/retrieve-account-for-clients', function (Request $request) use ($app) {
    $vars = json_decode($request->getContent(), true);
    $clients = isset($vars['clients']) ? $vars['clients'] : [];
    $month = isset($vars['month']) ? $vars['month'] : date('n');
    $year = isset($vars['year']) ? $vars['year'] : date('Y');

    $returnData = $app['contableData']->returnAccountsPerClients($clients, $month, $year);
    $response = [
        'success' => true,
        'error' => '',
        'data' => $returnData,
    ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->post('/api/retrieve-payments-for-clients', function (Request $request) use ($app) {
    $vars = json_decode($request->getContent(), true);
    $clients = isset($vars['clients']) ? $vars['clients'] : [];
    $month = isset($vars['month']) ? $vars['month'] : date('n');
    $year = isset($vars['year']) ? $vars['year'] : date('Y');

    die('not implemented');
    $returnData = $app['contableData']->returnAccountsPerClients($clients, $month, $year);
    $response = [
        'success' => true,
        'error' => '',
        'data' => $returnData,
    ];
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});


$app->post('/api/client-month-amount', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $year = null;
    $month = null;
    $clientId = null;
    $vars = json_decode($request->getContent(), true);
    if (!empty($vars['year'])) {
        $year = $vars['year'];
    }
    if (!empty($vars['month'])) {
        $month = $vars['month'];
    }
    if (!empty($vars['clientId'])) {
        $clientId = $vars['clientId'];
    }
    $returnData = [];
    if (empty($year) || empty($month) || empty($clientId)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $response = [
            'success' => true,
            'username' => $token->getUsername(),
            'clients' => $app['users']->loadClientByUsername($token->getUsername()), // CHECK
            //'username' => $token->getUser()->getId(),
            //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
        ];
        $isAllowed = false;
        foreach($response['clients'] as $client) {
            if ($client['permissions']['month-amount']) {
                $isAllowed = true;
            }

        }
        if ($isAllowed) {
            $returnData = $app['contableData']->returnPaymentsByClients([$clientId], $month, $year);
        }
    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('client-month-amount');

$app->get('/api/get-client-expiration/{clientId}', function ($clientId) use ($app) {

    $response = [
        'success' => false,
        'message' => '',
    ];
    $returnData = $app['contableData']->returnClientExpirations($clientId);
    if ($returnData['isvalid']) {
        $response['success'] = true;
        $response['data'] = $returnData['data'];
        $response['razon-social'] = $returnData['razonsocial'];
    }

    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('get-client-expiration-data');


$app->get('/api/get-public-available-tasks', function () use ($app) {

    $response = [
        'success' => true,
        'message' => '',
        'data' => $app['contableData']->returnPublicAvailableTasks()
    ];
    $returnData = $app['contableData']->returnPublicAvailableTasks();

    return $app->json($returnData, Response::HTTP_OK);
})->bind('get-public-available-tasks');

$app->post('/api/create-client-task', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    /** @var User $user */
    $user = $app['users']->loadUserByUsername($token->getUsername()); // CHECK
    $folder = null;
    $createdBy = null;
    $taskId = null;
    $comment = '';
    $vars = json_decode($request->getContent(), true);
    if (!empty($vars['folder'])) {
        $folder = $vars['folder'];
    }
    if (!empty($vars['createdBy'])) {
        $createdBy = $vars['createdBy'];
    }
    if (!empty($vars['taskId'])) {
        $taskId = $vars['taskId'];
    }
    if (!empty($vars['comment'])) {
        $comment = $vars['comment'];
    }
    $createdBy = $user->getUsername();
    if (empty($folder) || empty($createdBy) || empty($taskId)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $returnData = $app['contableData']->createPublicTask($folder, $createdBy, $taskId, $comment);
        $response = ['success' => true];

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('create-client-task');

$app->post('/api/retrieve-user-created-client-task', function (Request $request) use ($app) {
    //$token = $app['security.token_storage']->getToken();
    $all = null;
    $user = null;
    $vars = json_decode($request->getContent(), true);
    if (array_key_exists('all', $vars)) {
        $all = $vars['all'];
    }
    if (!empty($vars['user'])) {
        $user = $vars['user'];
    }
    $token = $app['security.token_storage']->getToken();
    /** @var User $user */
    $user = $app['users']->loadUserByUsername($token->getUsername()); // CHECK
    $username = $user->getUsername();
    if ($all === null) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $returnData = $app['contableData']->showUserPublicTask($all, $username);
        $response = ['success' => true];

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('retrieve-user-created-client-task');


$app->get('/api/profile', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $returnData = $app['users']->retrieveUserProfile($token->getUsername()); //CHECK
    return $app->json($returnData, Response::HTTP_OK);
})->bind('user-profile');


$app->post('/api/profile/update', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $response = [
        'success' => false,
    ];
    $returnData = ['message' => 'Bad params'];
    $vars = json_decode($request->getContent(), true);
    $email = isset($vars['email']) ? $vars['email'] : null;
    $firstName = isset($vars['firstName']) ? $vars['firstName'] : null;
    $lastName = isset($vars['lastName']) ? $vars['lastName'] : null;
    $username = isset($vars['username']) ? $vars['username'] : null;
    if (!empty($email) && !empty($firstName) && !empty($lastName) && !empty($username)) {
        $returnData['success'] = !empty($app['users']->updateUserProfile($token->getUsername(), $email, $firstName, $lastName, $username)); // CHECK
        $response['success'] = true;
        $returnData['message'] = '';
    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('edit-profile');

$app->post('/api/profile/change-password', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $response = [
        'success' => false,
    ];
    $returnData = ['message' => 'Bad params'];
    $vars = json_decode($request->getContent(), true);
    $currentPassword = isset($vars['currentPassword']) ? $vars['currentPassword'] : null;
    $newPassword = isset($vars['newPassword']) ? $vars['newPassword'] : null;
    if (!empty($currentPassword) && !empty($newPassword)) {
        $returnData['success'] = !empty($app['users']->updatePassword($token->getUsername(), $currentPassword, $newPassword)); // CHECK
        $response['success'] = true;
        $returnData['message'] = '';
    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('edit-profile');

$app->delete('/api/admin/profile/{id}', function (Request $request, $id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('admin-user-remove');

$app->get('/api/admin/get-groups', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('admin-get-groups');

$app->get('/api/admin/get-permission-types', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('admin-get-permission-types');

$app->get(' /api/admin/users-permissions-by-client/{folder}', function (Request $request, $folder) use ($app) {
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('admin-client-permissions');


$app->post('/api/admin/users', createUserListHandler($app, [
    'requireSuperuser' => true
]))->bind('admin-list-users');

$app->post('/api/admin/users-boss', createUserListHandler($app, [
    'useBossFilter' => true
]))->bind('boss-list-users');

function createUserListHandler($app, $options = [])
{
    return function (Request $request) use ($app, $options) {
        $returnData = ['message' => 'No permissions', 'data' => []];
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    };
}

$app->get('/api/admin/profile/{id}', createUserProfileHandler($app, 'admin'))
    ->bind('admin-user-profile');

$app->get('/api/admin/profile-boss/{id}', createUserProfileHandler($app, 'boss'))
    ->bind('boss-user-profile');

function createUserProfileHandler($app, $mode)
{
    return function (Request $request, $id) use ($app, $mode) {
        $errorResponse = ['message' => 'No permissions', 'data' => []];
        return $app->json($errorResponse, Response::HTTP_BAD_REQUEST);
    };
}

function createUpdateUserProfileHandler($app, $mode)
{
    return function (Request $request, $id) use ($app, $mode) {
        $errorResponse = ['message' => 'No permissions', 'data' => []];
        return $app->json($errorResponse, Response::HTTP_BAD_REQUEST);
    };
}
$app->post('/api/admin/profile-update/{id}', createUpdateUserProfileHandler($app, 'admin'))
    ->bind('admin-edit-profile');

$app->post('/api/admin/boss-profile-update/{id}', createUpdateUserProfileHandler($app, 'boss'))
    ->bind('boss-edit-profile');

$app->get('/api/admin/get-clients', function (Request $request) use ($app) {
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('admin-get-clients');

$app->get('/api/admin/boss-get-clients', function (Request $request) use ($app) {
    return $app->json([], Response::HTTP_OK);

})->bind('boss-get-clients');

$app->get('/api/admin/profile/permissions/{id}', function (Request $request, $id) use ($app) {
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('admin-user-profile-permissions');

$app->get('/api/admin/boss/profile/permissions/{id}', function (Request $request, $id) use ($app) {
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('boss-user-profile-permissions');


function createAssignPermissionHandler($app, $mode)
{
    return function (Request $request) use ($app, $mode) {
        $errorResponse = ['message' => 'No permissions', 'data' => []];
        return $app->json($errorResponse, Response::HTTP_BAD_REQUEST);
    };
}
$app->post('/api/admin/profile/permission/assign',
    createAssignPermissionHandler($app, 'admin')
)->bind('admin-user-permission-assign');

$app->post('/api/admin/boss/profile/permission/assign',
    createAssignPermissionHandler($app, 'boss')
)->bind('boss-user-permission-assign');

function createRemovePermissionHandler($app, $mode)
{
    return function (Request $request) use ($app, $mode) {
        $errorResponse = ['message' => 'No permissions', 'data' => []];
        return $app->json($errorResponse, Response::HTTP_BAD_REQUEST);
    };
}
$app->post('/api/admin/profile/permission/remove',
    createRemovePermissionHandler($app, 'admin')
)->bind('admin-user-permission-remove-assign');

$app->post('/api/admin/boss/profile/permission/remove',
    createRemovePermissionHandler($app, 'boss')
)->bind('boss-user-permission-remove-assign');

$app->post('/api/admin/users-with-permissions', function (Request $request) use ($app) {
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('admin-list-users-with-permissions');

$app->post('/api/admin/boss-users-with-permissions', function (Request $request) use ($app) {
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
})->bind('boss-list-users-with-permissions');

function handleCreateUser(Request $request, $app, callable $authorization, callable $resolveGroupClient)
{
    $returnData = ['message' => 'No permissions', 'data' => []];
    return $app->json($returnData, Response::HTTP_BAD_REQUEST);
}
$app->post('/api/admin/profile-create-user', function (Request $request) use ($app) {
    return $app->json(['message' => 'No permissions'], Response::HTTP_BAD_REQUEST);
})->bind('admin-create-user');

$app->post('/api/admin/boss-profile-create-user', function (Request $request) use ($app) {
    return $app->json(['message' => 'No permissions'], Response::HTTP_BAD_REQUEST);
})->bind('boss-create-user');

$app->get('/api/{id}/{fileId}/get-public-task-file', function (Request $request, $id, $fileId) use ($app) {
    $remoteResponse = $app['contableData']->retrieveTaskFile($id, $fileId);
    if ($remoteResponse->getStatusCode() === 404) {
        return new Response('File not found', 404);
    }

    if ($remoteResponse->getStatusCode() !== 200) {
        return new Response('Error retrieving file', 500);
    }

    $body = $remoteResponse->getBody();

    $response = new StreamedResponse(function () use ($body) {
        while (!$body->eof()) {
            echo $body->read(1024);
        }
    });
    // propagate headers
    $response->headers->set(
        'Content-Type',
        $remoteResponse->getHeaderLine('Content-Type')
    );

    $response->headers->set(
        'Content-Disposition',
        $remoteResponse->getHeaderLine('Content-Disposition')
    );
    foreach (['Content-Type','Content-Length','Content-Disposition'] as $header) {
        if ($remoteResponse->hasHeader($header)) {
            $response->headers->set($header, $remoteResponse->getHeaderLine($header));
        }
    }
    return $response;
})->bind('get-public-task-file');


$app->post('/api/add-comment-to-task', function (Request $request) use ($app) {
    $createdBy = null;
    $taskId = null;
    $comment = '';
    $vars = json_decode($request->getContent(), true);
    if (!empty($vars['createdBy'])) {
        $createdBy = $vars['createdBy'];
    }
    if (!empty($vars['taskId'])) {
        $taskId = $vars['taskId'];
    }
    if (!empty($vars['comment'])) {
        $comment = $vars['comment'];
    }
    $token = $app['security.token_storage']->getToken();
    /** @var User $user */
    $user = $app['users']->loadUserByUsername($token->getUsername()); // CHECK
    $createdBy = $user->getUsername();
    if (empty($comment) || empty($createdBy) || empty($taskId)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $returnData = $app['contableData']->addCommentToTask($createdBy, $taskId, $comment);
        $response = ['success' => true];

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('add-comment-to-task');

$app->post('/api/add-file-to-task/{id}', function (Request $request, $id) use ($app) {
    $file = $request->files->get('file');
    if (!$file) {
        return $app->json(array(
            'success' => false,
            'message' => 'No file uploaded'
        ), 400);
    }
    // Forward to Symfony application
    $result = $app['contableData']->uploadTaskFile(
        $id,
        $file
    );

    return $app->json($result);
})->bind('add-file-to-task');

$app->post('/api/migrate-users', function (Request $request) use ($app) {
    $vars = json_decode($request->getContent(), true);
    $token = $vars['token'] ?: '';
    try {
        $app['users']->migrateAllUsers($token);
        $response = [
            'success' => true,
        ];
        $returnData = ['message' => 'Usuarios migrados'];
    } catch (\Exception $e) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => $e->getMessage()];
    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('migrate-users');