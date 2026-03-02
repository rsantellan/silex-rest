<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;

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
            'clients' => $app['users']->loadClientByUsername($token->getUsername()),
            //'username' => $token->getUser()->getId(),
            //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
        ];
        $clientId = null;
        if (count($response['clients']) > 0) {
            $first = array_pop($response['clients']);
            $clientId = $first['id'];
        }

        if ($clientId) {
            $returnData = $app['contableData']->returnPayments($clientId, $month, $year);
            if ($returnData['isvalid']) {
                $removeClientList = [];
                $allClientList = [];
                $permissionData = $app['users']->getPermissionOfUser($token->getUsername(), 'monthAmount');
                foreach ($returnData['data'] as $clientId => $clientData) {
                    $allClientList[] = $clientId;
                    if (!in_array($clientId, $permissionData)) {
                        $removeClientList[] = $clientId;
                    }
                }
                foreach ($removeClientList as $clientId) {
                    unset($returnData['data'][$clientId]);
                }
            }
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
            'clients' => $app['users']->loadClientByUsername($token->getUsername()),
            //'username' => $token->getUser()->getId(),
            //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
        ];
        $found = false;
        $permissionData = $app['users']->getPermissionOfUser($token->getUsername(), 'accounts');
        if (count($response['clients']) > 0) {
            foreach ($response['clients'] as $client) {
                if ($client['folder_number'] == $folder) {
                    if (in_array($client['id'], $permissionData)) {
                        $found = true;
                    } else {
                        $forbidden = true;
                    }
                }
            }
        }
        //$found = true;
        //$returnData = [];
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

        $userData = $app['users']->loadUserByUsernameComplete($vars['_username']);
        /**
         * @var $user User
         */
        $user = $userData['user'];
        $dbData = $userData['data'];
        $children = $userData['children'];
        if (!$app['security.default_encoder']->isPasswordValid($user->getPassword(), $vars['_password'], '')) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist 2.', $vars['_username']));
        } else {
            $app['users']->updateLoggedUserData($vars['_username'], $user->getUsername());
            $response = [
                'success' => true,
                'error' => '',
                'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
                'user' => [
                    'username' => $dbData['username'],
                    'group' => $dbData['group_id'],
                    //'boss'  => $dbData['group_boss'],
                    'superuser'  => $dbData['superuser'],
                    'firstName'  => $dbData['first_name'],
                    'lastName'  => $dbData['last_name'],
                    'children' => $children,
                ]
            ];
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
        $clients = $app['users']->loadClientByUsername($token->getUsername());
        $data = [];
        $today = new \DateTime();
        $hash = md5($today->format('Y-m-d'));
        $permissionData = $app['users']->getPermissionOfUser($token->getUsername(), 'files');
        foreach ($clients as $client)
        {
            $valid = false;
            if (is_array($permissionData)) {
                if (in_array($client['id'], $permissionData)) {
                    $valid = true;
                }
            } else {
                if ($client['id'] == $permissionData) {
                    $valid = true;
                }
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
    /** @var User $user */
    $user = $app['users']->loadUserByUsername($token->getUsername());
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
            $responseData = $app['clientData']->sendPaymentFile($user->getUsername(), $text, $amount, $fileName, $newFileName, $dest_path);
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
        $clients = $app['users']->loadClientByUsername($token->getUsername());
        $data = [];
        $permissionData = $app['users']->getPermissionOfUser($token->getUsername(), 'monthAmount');
        foreach ($clients as $client)
        {
            if (in_array($client['id'], $permissionData)) {
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
        $clients = $app['users']->loadClientByUsername($token->getUsername());
        $data = [];
        $permissionData = $app['users']->getPermissionOfUser($token->getUsername(), 'certificates');
        foreach ($clients as $client)
        {
            $valid = false;
            if (is_array($permissionData)) {
                if (in_array($client['id'], $permissionData)) {
                    $valid = true;
                }
            } else {
                if ($client['id'] == $permissionData) {
                    $valid = true;
                }
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
    $titleIsValid = isset($vars['title']) && !empty($vars['title']);
    $bodyIsValid = isset($vars['body']) && !empty($vars['body']);
    $usersIsValid = isset($vars['users']) && !empty($vars['users']) && is_array($vars['users']);
    if ($titleIsValid && $bodyIsValid && $usersIsValid) {
        $users = [];
        foreach ($vars['users'] as $dirtyUser) {
            $users[$app['users']->getPushUser($dirtyUser)] = $app['users']->getPushUser($dirtyUser);
        }
        $title = $vars['title'];
        $body = $vars['body'];
        // Add Title to Body.
        $body = $title.'</br>'.$body;
        $returnData = $app['pushapi']->doPush($title, $body, $users);
        $response['fullData'] = $returnData;
        if (!empty($returnData)) {
            $data = json_decode($returnData);
            if ($data->status === 200) {
                $response['success'] = true;
                $response['pushes'] = $data->pushes;
            }
        }
    }
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
    if (isset($vars['folderdata']) && !empty($vars['folderdata'])) {
        $response['success'] = true;
        $response['users'] = $app['users']->folderHasAppUser($vars['folderdata']);
    }
    return $app->json($response, ($response['success'] == true ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
});

$app->get('/send-data/{password}', function ($password) use ($app) {
    $response = [
        'success' => false,
        'error' => 'Error',
    ];
    if ($password === PASSWORD_PUSH_CODE) {
        $response['success'] = true;
        $response['error'] = '';
        $loggedUsers = $app['users']->getAllLoggedUsernames();
        $users = [];
        foreach ($loggedUsers as $user) {
            $username = $user['username'];
            $email = $user['email'];
            $clients = $app['users']->loadClientByUsername($email);
            $year = (int)date('Y');
            $month = (int)date('n');
            // Asumo Payments
            $clientId = null;
            if (count($clients) > 0) {
                $first = array_pop($clients);
                $clientId = $first['id'];
            }
            if ($clientId) {
                $oldFilesName = SAVE_DATA_FILES . '/' . sprintf('%s-%s-%s', $clientId, $month, $year);
                $returnData = md5(serialize($app['contableData']->returnPayments($clientId, $month, $year)));
                $oldHash = null;
                if (file_exists($oldFilesName)) {
                    $oldHash = file_get_contents($oldFilesName);
                }
                // Compare to a saved file.
                if ($oldHash === $returnData) {
                    // do nothing
                } else {
                    // Send message
                    $sendMessage = true;
                    $users[$username] = $username;
                    // Save again to file
                    //file_put_contents($oldFilesName, $returnData);
                }
            }

        }
        if (count($users) > 0) {
            $title = 'This is a title';
            $body = 'This is a body';
            $returnData = $app['pushapi']->doPush($title, $body, $users);
            file_put_contents('/tmp/aux.log', $returnData);
        }
    }
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
            'clients' => $app['users']->loadClientByUsername($token->getUsername()),
            //'username' => $token->getUser()->getId(),
            //'token' => $app['security.jwt.encoder']->encode(['name' => $user->getUsername()]),
        ];


        $returnData = $app['contableData']->returnPayments($clientId, $month, $year);
        if ($returnData['isvalid']) {
            $removeClientList = [];
            $allClientList = [];
            $permissionData = $app['users']->getPermissionOfUser($token->getUsername(), 'monthAmount');
            foreach ($returnData['data'] as $clientId => $clientData) {
                $allClientList[] = $clientId;
                if (!in_array($clientId, $permissionData)) {
                    $removeClientList[] = $clientId;
                }
            }
            foreach ($removeClientList as $clientId) {
                unset($returnData['data'][$clientId]);
            }
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
    $folder = null;
    $createdBy = null;
    $taskId = null;
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
    if (empty($folder) || empty($createdBy) || empty($taskId)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $returnData = $app['contableData']->createPublicTask($folder, $createdBy, $taskId);
        $response = ['success' => true];

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('create-client-task');

$app->post('/api/retrieve-user-created-client-task', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $all = null;
    $user = null;
    $vars = json_decode($request->getContent(), true);
    if (array_key_exists('all', $vars)) {
        $all = $vars['all'];
    }
    if (!empty($vars['user'])) {
        $user = $vars['user'];
    }
    if ($all === null || empty($user)) {
        $response = [
            'success' => false,
        ];
        $returnData = ['message' => 'Bad params'];
    } else {
        $returnData = $app['contableData']->showUserPublicTask($all, $user);
        $response = ['success' => true];

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('retrieve-user-created-client-task');


$app->get('/api/profile', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $returnData = $app['users']->retrieveUserProfile($token->getUsername());
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
        $returnData['success'] = $app['users']->updateUserProfile($token->getUsername(), $email, $firstName, $lastName, $username);
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
        $userData = $app['users']->loadUserByUsernameComplete($token->getUsername());
        /**
         * @var $user User
         */
        $user = $userData['user'];
        if (!$app['security.default_encoder']->isPasswordValid($user->getPassword(), $currentPassword, '')) {
            throw new \Exception('Current password is incorrect');
        }
        $returnData['success'] = $app['users']->updatePassword($token->getUsername(), $app['security.default_encoder']->encodePassword($newPassword, $user->getSalt()));
        $response['success'] = true;
        $returnData['message'] = '';
    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('edit-profile');

$app->post('/api/admin/users', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $response = [
        'success' => false
    ];
    $returnData = ['message' => 'No permissions', 'data' => []];
    $vars = json_decode($request->getContent(), true);
    $page = isset($vars['page']) ? (int)$vars['page'] : 1;
    $limit = isset($vars['limit']) ? (int)$vars['limit'] : 20;
    $search = isset($vars['search']) ? $vars['search'] : null;

    $userData = $app['users']->loadDbUser($token->getUsername());
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    if ($page < 1) {
        $page = 1;
    }
    if (!empty($search)) {
        $search = '%'.$search.'%';
    }
    $offset = ($page - 1) * $limit;
    $returnData['data'] = $app['users']->getUserList($search, $limit, $offset);
    $response['success'] = true;
    $returnData['message'] = '';

    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('admin-list-users');


$app->get('/api/admin/profile/{id}', function (Request $request, $id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());
    $returnData = ['message' => 'No permissions', 'data' => []];
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $returnData = $app['users']->retrieveUserProfileById($id);
    return $app->json($returnData, Response::HTTP_OK);
})->bind('admin-user-profile');


$app->post('/api/admin/profile-update/{id}', function (Request $request, $id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());
    $returnData = ['message' => 'No permissions', 'data' => []];
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
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
        $returnData['success'] = $app['users']->updateUserProfileById($id, $email, $firstName, $lastName, $username);
        $response['success'] = true;
        $returnData['message'] = '';
    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('admin-edit-profile');

$app->delete('/api/admin/profile/{id}', function (Request $request, $id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());
    $returnData = ['message' => 'No permissions', 'data' => []];
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $returnData['message'] = '';
    $returnData['success'] = $app['users']->doDeleteUserById($id);
    return $app->json($returnData, Response::HTTP_OK);
})->bind('admin-user-remove');

$app->get('/api/admin/get-groups', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());

    if ((int)$userData['superuser'] !== 1) {
        $returnData = ['message' => 'No permissions', 'data' => []];
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $returnData = $app['contableData']->retrieveAllGroups();

    return $app->json($returnData, Response::HTTP_OK);
})->bind('admin-get-groups');

$app->get('/api/admin/get-clients', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());

    if ((int)$userData['superuser'] !== 1) {
        $returnData = ['message' => 'No permissions', 'data' => []];
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $returnData = $app['contableData']->retrieveAlClients();

    return $app->json($returnData, Response::HTTP_OK);
})->bind('admin-get-clients');

$app->post('/api/admin/profile-create-user', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());
    $returnData = ['message' => 'No permissions', 'data' => []];
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $response = [
        'success' => false,
    ];
    $returnData = ['message' => 'Bad params'];
    $vars = json_decode($request->getContent(), true);

    $email = isset($vars['email']) ? $vars['email'] : null;
    $firstName = isset($vars['firstName']) ? $vars['firstName'] : null;
    $lastName = isset($vars['lastName']) ? $vars['lastName'] : null;
    $username = isset($vars['username']) ? $vars['username'] : null;
    $status = isset($vars['status']) ? $vars['status'] : 1;
    $password = isset($vars['password']) ? $vars['password'] : null;
    $groupId = isset($vars['group_id']) ? $vars['group_id'] : null;
    $clientId = isset($vars['client_id']) ? $vars['client_id'] : null;

    //var_dump($vars);
    if (!empty($password) && !empty($email) && !empty($firstName) && !empty($lastName) && !empty($username)) {
        try {
            $returnData['newUser'] = $app['users']->createNewUser($username, $firstName, $lastName, $email, $password, $status, $groupId, $clientId, $userData['id']);
            $response['success'] = true;
            $returnData['message'] = '';
            $returnData['success'] = true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $returnData = ['message' => 'Datos duplicados'];
        }

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('admin-create-user');

$app->get('/api/admin/get-permission-types', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());

    if ((int)$userData['superuser'] !== 1) {
        $returnData = ['message' => 'No permissions', 'data' => []];
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $returnData = $app['users']->getAllPermissionTypes();

    return $app->json($returnData, Response::HTTP_OK);
})->bind('admin-get-permission-types');

$app->get('/api/admin/profile/permissions/{id}', function (Request $request, $id) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());
    $returnData = ['message' => 'No permissions', 'data' => []];
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $returnData['message'] = '';
    $returnData['data'] = $app['users']->retrieveAllUserPermissions($id);
    $returnData['success'] = true;
    return $app->json($returnData, Response::HTTP_OK);
})->bind('admin-user-profile-permissions');


$app->post('/api/admin/profile/permission/assign', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());
    $returnData = ['message' => 'No permissions', 'data' => []];
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $response = [
        'success' => false,
    ];
    $returnData = ['message' => 'Bad params'];
    $vars = json_decode($request->getContent(), true);

    $folder = isset($vars['folder']) ? $vars['folder'] : null;
    $type = isset($vars['type']) ? $vars['type'] : null;
    $userId = isset($vars['userId']) ? $vars['userId'] : null;

    //var_dump($vars);
    if (!empty($folder) && !empty($type) && !empty($userId)) {
        try {
            $returnData['permissions'] = $app['users']->addPermissionToUser($userId, $folder, $type);
            $response['success'] = true;
            $returnData['message'] = '';
            $returnData['success'] = true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $returnData = ['message' => 'Datos duplicados'];
        }

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('admin-user-permission-assign');

$app->post('/api/admin/profile/permission/remove', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $userData = $app['users']->loadDbUser($token->getUsername());
    $returnData = ['message' => 'No permissions', 'data' => []];
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    $response = [
        'success' => false,
    ];
    $returnData = ['message' => 'Bad params'];
    $vars = json_decode($request->getContent(), true);

    $folder = isset($vars['folder']) ? $vars['folder'] : null;
    $type = isset($vars['type']) ? $vars['type'] : null;
    $userId = isset($vars['userId']) ? $vars['userId'] : null;

    //var_dump($vars);
    if (!empty($folder) && !empty($type) && !empty($userId)) {
        try {
            $returnData['permissions'] = $app['users']->removePermissionOfUser($userId, $folder, $type);
            $response['success'] = true;
            $returnData['message'] = '';
            $returnData['success'] = true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            $returnData = ['message' => 'Datos duplicados'];
        }

    }
    return $app->json($returnData, ($response['success'] ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST));
})->bind('admin-user-permission-remove-assign');

$app->post('/api/admin/users-with-permissions', function (Request $request) use ($app) {
    $token = $app['security.token_storage']->getToken();
    $response = [
        'success' => false
    ];
    $returnData = ['message' => 'No permissions', 'data' => []];
    $vars = json_decode($request->getContent(), true);
    $page = max(1, isset($vars['page']) ? (int)$vars['page'] : 1);
    $limit = min(50, isset($vars['perPage']) ? (int)$vars['perPage'] : 20);
    $search = isset($vars['search']) ? $vars['search'] : null;

    $userData = $app['users']->loadDbUser($token->getUsername());
    if ((int)$userData['superuser'] !== 1) {
        return $app->json($returnData, Response::HTTP_BAD_REQUEST);
    }
    if ($page < 1) {
        $page = 1;
    }
    if (!empty($search)) {
        $search = '%'.$search.'%';
    }
    $offset = ($page - 1) * $limit;
    $returnData = $app['users']->getUserPermissionsList($search, $limit, $offset);
    return $app->json($returnData, Response::HTTP_OK);
})->bind('admin-list-users-with-permissions');