<?php

namespace Maith\Security;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Doctrine\DBAL\Connection;

class UserProvider implements UserProviderInterface
{
    private $conn;
    private $clientConn;

    public function __construct(Connection $conn, Connection $clientConn)
    {
        $this->conn = $conn;
        $this->clientConn = $clientConn;
    }

    public function loadUserByUsername($username)
    {
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
        return new User($user['email'], $user['password'], array('1'), true, true, true, true);
    }

    public function saveLoadedUsername($username)
    {
        $clients = $this->loadClientByUsername($username);
        $folderList = ",";
        foreach($clients as $client){
            $folderList .= $client['folder_number'].",";
        }
        $sql = 'replace into mobile_users (username, folderdata) values (?, ?)';
        $this->conn->executeUpdate($sql, array($username, $folderList));
        return true;
    }

    public function getAllLoggedUsernames()
    {
        $sql = 'select email, username from tbl_users where email in (select username from mobile_users)';
        return $this->conn->fetchAll($sql);
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'Symfony\Component\Security\Core\User\User';
    }

    public function loadClientByUsername($email)
    {
        try{
            $sql = 'select id, username, password, email, status, group_id, group_boss, client_id from tbl_users where email = ?';
            $stmt = $this->conn->executeQuery($sql, array($email));
            $data = $stmt->fetch();
            $clientList = [];
            if(!empty($data['group_id']))
            {
                $sqlClientId = 'select id, folder_number, social_reason from ec_clients where id_group = ?';
                $stmt = $this->clientConn->executeQuery($sqlClientId, array($data['group_id']));
                $clientList = $stmt->fetchAll();
            }
            if(!empty($data['client_id']))
            {
                $sqlClientId = 'select id, folder_number, social_reason from ec_clients where id = ?';
                $stmt = $this->clientConn->executeQuery($sqlClientId, array($data['client_id']));
                $client = $stmt->fetch();
                if(!empty($client)){
                    $clientList[] = $client;
                }
            }
            return $clientList;
        }catch(\Exception $e){
            //var_dump($e->getMessage());
        }
        return [];
    }

    public function folderHasAppUser($folder)
    {
        $userList = [];
        try{
            $sql = 'select email, username from tbl_users where email in (select username from mobile_users where folderdata like ?)';
            $stmt = $this->conn->executeQuery($sql, array('%'.$folder.'%'));
            $userList = $stmt->fetchAll();
        }catch(\Exception $e){

        }
        return $userList;
    }
}
