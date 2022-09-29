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

    /**
     * @param $username
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function loadDbUser($username) {
        $sql = 'select id, username, password, email, status, group_id, group_boss, client_id from tbl_users where ';
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
        foreach($clients as $client){
            $folderList .= $client['folder_number'].",";
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
            $accountsPermissions = $this->getPermissionOfUser($data['email'], 'accounts');
            $certificatesPermissions = $this->getPermissionOfUser($data['email'], 'certificates');
            $filesPermissions = $this->getPermissionOfUser($data['email'], 'files');
            $fullClientData = $this->clientData->getClientData($data['client_id'], $data['group_id']);
            foreach ($fullClientData as $client) {
                $services = [
                    'month-amount' => $this->checkClientInPermissionList($client['id'], $monthAmountPermissions),
                    'current-account-data' => $this->checkClientInPermissionList($client['id'], $accountsPermissions),
                    'files' => $this->checkClientInPermissionList($client['id'], $filesPermissions),
                    'certificates' => $this->checkClientInPermissionList($client['id'], $certificatesPermissions),

                ];
                $client['permissions'] = $services;
                $clientList[] = $client;
            }
            return $clientList;
        }catch(\Exception $e){
            var_dump($e->getMessage());
        }
        return [];
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
}
