<?php

namespace Maith\Security;

use Assetic\Filter\PackagerFilter;
use GuzzleHttp\Client;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Doctrine\DBAL\Connection;

use Maith\Data\ClientData;

class UserProvider implements UserProviderInterface
{
    private $conn;
    private $clientData;
    private $baseUrl;
    private $token;

    /**
     * UserProvider constructor.
     * @param Connection $conn
     * @param ClientData $clientData
     * @param string $baseUrl
     * @param string $token
     */
    public function __construct(Connection $conn, ClientData $clientData, $baseUrl, $token)
    {
        $this->conn = $conn;
        $this->clientData = $clientData;
        $this->baseUrl = $baseUrl;
        $this->token = $token;
    }

    /**
     * @param string $username
     * @return User|UserInterface
     * @throws \Doctrine\DBAL\DBALException
     */
    public function loadUserByUsername($username)
    {
        $user = $this->externalReloadUser($username);
        if ($user) {
            return $user;
        }
        throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
    }

    /**
     * @param UserInterface $user
     * @return User|UserInterface
     * @throws \Doctrine\DBAL\DBALException
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * @param string $class
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }

    /**
     * @param $email
     * @return array
     */
    public function loadClientByUsername($email, $withGlobal = false)
    {
        $data = $this->userClients($email);
        $allData = $data['data'];
        $global = null;
        $clients = [];
        foreach ($data['data'] as $key => $datum) {
            if ($key === 'global') {
                $global = $datum;
            } else {
                $clients[] = $datum;
            }

        }
        if ($withGlobal) {
            return [
                'global' => $global,
                'clients' => $clients,
            ];
        }
        return $clients;
    }

    public function retrieveUserProfile($username)
    {
        $userData = $this->externalRetrieveUser($username);
        return [
            'username' => $userData['username'],
            'email' => $userData['email'],
            'firstName' => $userData['firstName'],
            'lastName' => $userData['lastName'],
            'group' => null,
            'startingDate' => $userData['createdAt'],
            'lastVisit' => $userData['lastLogin'],
            'status' => ($userData['status'] == 0 ? 'Inactivo' : 'Activo'),
            'group_boss' => 0,
        ];
    }

    public function updateUserProfile($oldUser, $email, $firstName, $lastName, $username)
    {
        $url = $this->baseUrl.'/public/security/retrieve-client-roles';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => ['old_username' => $oldUser, 'new_username' => $username, 'first_name' => $firstName, 'last_name' => $lastName, 'email' => $email],
        ]);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        }
        return [];
    }

    public function updatePassword($username, $oldPassword, $newPassword) {
        $url = $this->baseUrl.'/public/security/update-password';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => ['username' => $username, 'old_password' => $oldPassword, 'password' => $newPassword],
        ]);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        }
        return [];
    }

    public function migrateAllUsers($token)
    {
        if ($token !== $this->token) {
            throw new \Exception('Security error');
        }
        $sql = 'select u.id, u.username, u.password, u.email, u.status, u.superuser, u.group_id, u.group_boss, u.client_id, u.create_at, u.lastvisit_at, p.first_name, p.last_name from tbl_users u left join tbl_profiles p on p.user_id = u.id';
        $rows = $this->conn->fetchAll($sql);
        $responseData = [];
        foreach ($rows as $row) {
            $row['clients'] = $this->loadClientByUsername($row['email']);
            $responseData[] = $this->migrateUser($row);
        }
        return $responseData;
    }

    public function migrateUser($userData)
    {
        $url = $this->baseUrl.'/public/security/migrate-user';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => [
                'data' => $userData
            ]
        ]);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        }
        return [];
    }


    public function userClients($email)
    {
        $url = $this->baseUrl.'/public/security/retrieve-client-roles';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => ['email' => $email]
        ]);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        }
        return [];
    }
    public function externalLogin($username, $password)
    {
        $url = $this->baseUrl.'/public/security/login';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => ['username' => $username, 'password' => $password]
        ]);
        if ($response) {
            switch ($response->getStatusCode()) {
                case 200:
                    $content = json_decode($response->getBody()->getContents(), true);
                    return
                        [
                            'user' => new User($content['user']['email'], $content['user']['email'], array('1'), true, true, true, true),
                            'data' => $content['user'],
                            'clients' => $content['clients'],
                        ];
                    break;
                default:
                    throw new \Exception('Unauthorized');
                    break;
            }
        }
        return [];
    }

    public function externalRetrieveUser($username)
    {
        $url = $this->baseUrl.'/public/security/reload-user';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => ['username' => $username]
        ]);
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    public function externalReloadUser($username)
    {
        $userData = $this->externalRetrieveUser($username);
        if ($userData) {
            return new User($userData['user']['email'], $userData['user']['email'], array('1'), true, true, true, true);
        }
        return [];
    }
}
