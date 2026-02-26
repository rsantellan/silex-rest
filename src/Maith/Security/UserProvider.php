<?php

namespace Maith\Security;

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

    /**
     * UserProvider constructor.
     * @param Connection $conn
     * @param Connection $clientConn
     */
    public function __construct(Connection $conn, ClientData $clientData)
    {
        $this->conn = $conn;
        $this->clientData = $clientData;
    }

    /**
     * @param string $username
     * @return User|UserInterface
     * @throws \Doctrine\DBAL\DBALException
     */
    public function loadUserByUsername($username)
    {
        $user = $this->loadDbUser($username);
        return new User($user['email'], $user['password'], array('1'), true, true, true, true);
    }

    public function loadUserByUsernameComplete($username) {
        $user = $this->loadDbUser($username);
        $children = $this->loadChilds($user['id']);
        return
            [
                'user' => new User($user['email'], $user['password'], array('1'), true, true, true, true),
                'data' => $user,
                'children' => $children,
            ];
    }
    /**
     * @param $username
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function loadDbUser($username) {
        $sql = 'select u.id, u.username, u.password, u.email, u.status, u.superuser, u.group_id, u.group_boss, u.client_id, u.create_at, u.lastvisit_at, p.first_name, p.last_name from tbl_users u left join tbl_profiles p on p.user_id = u.id where ';
        if (strpos($username,"@")) {
            $sql .= 'email = ?';
        }else{
            $sql .= 'username = ?';
        }
        $stmt = $this->conn->executeQuery($sql, array($username));
        if (!$user = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $username));
        }
        return $user;
    }

    /**
     * @param int $parentId
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function loadChilds($parentId) {
        $sql = 'select u.id, u.username, u.email, u.status, u.superuser, u.group_id, u.client_id, p.first_name, p.last_name from tbl_users u left join tbl_profiles p on p.user_id = u.id where u.group_boss = ?';
        $stmt = $this->conn->executeQuery($sql, array($parentId));
        $users = [];
        while ($child = $stmt->fetch()) {
            $moreChildren = $this->loadChilds($child['id']);
            unset($child['id']);
            $users[] = $child;
            $users = array_merge($users, $moreChildren);
        }
        return $users;
    }
    /**
     * @param $loggedUsername
     * @param $email
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateLoggedUserData($loggedUsername, $email)
    {
        $user = $this->loadDbUser($email);
        $this->updateUsedAuthMethod($email, $user['username'], $loggedUsername);
        $this->saveLoadedUsername($email);
    }

    /**
     * @param $email
     * @param $username
     * @param $loggedUsername
     * @throws \Doctrine\DBAL\DBALException
     */
    public function updateUsedAuthMethod($email, $username, $loggedUsername)
    {
        if (strpos($loggedUsername,"@")) {
            $method = 0;
        }else{
            $method = 1;
        }
        $sql = 'replace into mobile_used_users (email, username, method) values (?, ?, ?)';
        $this->conn->executeUpdate($sql, array($email, $username, $method));
    }

    /**
     * @param $email
     * @return bool
     * @throws \Doctrine\DBAL\DBALException
     */
    public function saveLoadedUsername($email)
    {
        $clients = $this->loadClientByUsername($email);
        $folderList = ",";
        if (!empty($clients)) {
            foreach($clients as $client){
                $folderList .= $client['folder_number'].",";
            }
        }
        $sql = 'update mobile_used_users set folderdata = ? where email = ?';
        $this->conn->executeUpdate($sql, array($folderList, $email));
        return true;
    }

    /**
     * @return mixed[]
     */
    public function getAllLoggedUsernames()
    {
        $sql = 'select email, username from tbl_users where email in (select username from mobile_used_users)';
        return $this->conn->fetchAll($sql);
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
    public function loadClientByUsername($email)
    {
        try{
            $sql = 'select id, username, password, email, status, group_id, group_boss, client_id from tbl_users where email = ?';
            $stmt = $this->conn->executeQuery($sql, array($email));
            $data = $stmt->fetch();
            $clientList = [];
            $monthAmountPermissions = $this->getPermissionOfUser($data['email'], 'monthAmount');
            //var_dump($monthAmountPermissions);
            $accountsPermissions = $this->getPermissionOfUser($data['email'], 'accounts');
            //var_dump($accountsPermissions);
            $certificatesPermissions = $this->getPermissionOfUser($data['email'], 'certificates');
            //var_dump($certificatesPermissions);
            $filesPermissions = $this->getPermissionOfUser($data['email'], 'files');
            //var_dump($filesPermissions);
            $fullClientData = $this->clientData->getClientData($data['client_id'], $data['group_id']);
            //var_dump($fullClientData);die;
            $dbClientList = [];
            $dbClientList = $this->mergeClientIds($dbClientList, $monthAmountPermissions);
            $dbClientList = $this->mergeClientIds($dbClientList, $accountsPermissions);
            $dbClientList = $this->mergeClientIds($dbClientList, $certificatesPermissions);
            $dbClientList = $this->mergeClientIds($dbClientList, $filesPermissions);
            $usedClientList = [];
            if (!empty($fullClientData)) {
                foreach ($fullClientData as $client) {
                    $services = [
                        'month-amount' => $this->checkClientInPermissionList($client['id'], $monthAmountPermissions),
                        'current-account-data' => $this->checkClientInPermissionList($client['id'], $accountsPermissions),
                        'files' => $this->checkClientInPermissionList($client['id'], $filesPermissions),
                        'certificates' => $this->checkClientInPermissionList($client['id'], $certificatesPermissions),

                    ];
                    $client['permissions'] = $services;
                    $clientList[] = $client;
                    $usedClientList[] = $client['id'];
                    if (array_key_exists($client['id'], $dbClientList)) {
                        unset($dbClientList[$client['id']]);
                    }
                }
            }
            //var_dump($usedClientList);
            if (!empty($dbClientList)) {
                $fullOfDbClientData = $this->clientData->getClientDataByIdList($dbClientList);
                if (!empty($fullOfDbClientData)) {
                    foreach ($fullOfDbClientData as $client) {
                        //var_dump($client['id']);
                        //var_dump($usedClientList);
                        if (!in_array($client['id'], $usedClientList)) {
                            $services = [
                                'month-amount' => $this->checkClientInPermissionList($client['id'], $monthAmountPermissions),
                                'current-account-data' => $this->checkClientInPermissionList($client['id'], $accountsPermissions),
                                'files' => $this->checkClientInPermissionList($client['id'], $filesPermissions),
                                'certificates' => $this->checkClientInPermissionList($client['id'], $certificatesPermissions),

                            ];
                            $client['permissions'] = $services;
                            $clientList[] = $client;
                            $usedClientList[] = $client['id'];
                        }
                    }
                }
            }
            return $clientList;
        }catch(\Exception $e){
            var_dump($e->getMessage());
        }
        return [];
    }

    private function mergeClientIds($list, $permissionList)
    {
        if (is_array($permissionList)) {
            $list = array_merge($list, $permissionList);
        } else {
            $list[] = $permissionList;
        }
        return $list;
    }

    private function checkClientInPermissionList($clientId, $permissionList)
    {
        $valid = false;
        if (is_array($permissionList)) {
            if (in_array($clientId, $permissionList)) {
                $valid = true;
            }
        } else {
            if ($clientId == $permissionList) {
                $valid = true;
            }
        }
        return $valid;
    }

    /**
     * @param $email
     * @param $section
     * @return array|mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getPermissionOfUser($email, $section)
    {
        $sql = "select data from AuthAssignment where itemname = ? and userid in (select id from tbl_users where email = ?) limit 1";
        $stmt = $this->conn->executeQuery($sql, [$section, $email]);
        $data = $stmt->fetch();
        if (!empty($data['data'])) {
            return unserialize($data['data']);
        }
        return [];
    }

    /**
     * @param $folder
     * @return array|mixed[]
     */
    public function folderHasAppUser($folder)
    {
        $userList = [];
        try{
            $sql = 'select email, username from tbl_users where email in (select email from mobile_used_users where folderdata like ?)';
            $stmt = $this->conn->executeQuery($sql, array('%'.$folder.'%'));
            $userList = $stmt->fetchAll();
        }catch(\Exception $e){

        }
        return $userList;
    }

    /**
     * @param $user
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getPushUser($user)
    {
        $sql = 'select username, email, method from mobile_used_users where ';
        if (strpos($user,"@")) {
            $sql .= 'email = ?';
        }else{
            $sql .= 'username = ?';
        }
        $stmt = $this->conn->executeQuery($sql, array($user));
        if (!$dbUser = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Username "%s" does not exist.', $user));
        }
        if ($dbUser['method'] == 0) {
            return $dbUser['email'];
        } else {
            return $dbUser['username'];
        }
    }

    public function retrieveUserProfile($username)
    {
        $dbUser = $this->loadDbUser($username);
        $clientAndGroup = $this->clientData->retrieveGroupOrClientData($dbUser['client_id'], $dbUser['group_id']);
        return [
            'username' => $dbUser['username'],
            'email' => $dbUser['email'],
            'firstName' => $dbUser['first_name'],
            'lastName' => $dbUser['last_name'],
            'group' => $clientAndGroup['group'],
            'startingDate' => $dbUser['create_at'],
            'lastVisit' => $dbUser['lastvisit_at'],
            'status' => ($dbUser['status'] == 0 ? 'Inactivo' : 'Activo'),
        ];
    }

    public function updateUserProfile($oldUser, $email, $firstName, $lastName, $username)
    {
        $dbUser = $this->loadDbUser($username);
        // $sql = 'select u.id, u.username, u.password, u.email, u.status, u.superuser, u.group_id, u.group_boss, u.client_id, u.create_at, u.lastvisit_at, p.first_name, p.last_name from tbl_users u left join tbl_profiles p on p.user_id = u.id where ';
        $sqlUpdateProfile = 'update tbl_profiles set first_name = ?, last_name = ? where user_id = ?';
        $this->conn->executeUpdate($sqlUpdateProfile, array($firstName, $lastName, $dbUser['id']));
        $sqlUpdate = 'update tbl_users set username = ?, email = ? where id = ?';
        $this->conn->executeUpdate($sqlUpdate, array($username, $email, $dbUser['id']));
        return true;
    }

    public function updatePassword($username, $newPassword) {
        $dbUser = $this->loadDbUser($username);
        $sqlUpdate = 'update tbl_users set password = ? where id = ?';
        $this->conn->executeUpdate($sqlUpdate, array($newPassword, $dbUser['id']));
        return true;
    }

}
