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

    public function loadDbUserById($id) {
        $sql = 'select u.id, u.username, u.password, u.email, u.status, u.superuser, u.group_id, u.group_boss, u.client_id, u.create_at, u.lastvisit_at, p.first_name, p.last_name from tbl_users u left join tbl_profiles p on p.user_id = u.id where ';
        $sql .= 'u.id = ?';
        $stmt = $this->conn->executeQuery($sql, array($id));
        if (!$user = $stmt->fetch()) {
            throw new UsernameNotFoundException(sprintf('Id "%s" does not exist.', $id));
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
     * @param $email
     * @param $section
     * @return array|mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getPermissionOfUserById($userId, $section)
    {
        $sql = "select data from AuthAssignment where itemname = ? and userid = ?";
        $stmt = $this->conn->executeQuery($sql, [$section, $userId]);
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
        return $this->returnUserProfileByData($dbUser);
    }
    public function retrieveUserProfileById($id)
    {
        $dbUser = $this->loadDbUserById($id);
        return $this->returnUserProfileByData($dbUser);
    }

    private function returnUserProfileByData($dbUser)
    {
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
        $dbUser = $this->loadDbUser($oldUser);
        return $this->updateUserProfileById($dbUser['id'], $email, $firstName, $lastName, $username);
    }

    public function updateUserProfileById($id, $email, $firstName, $lastName, $username)
    {
        $sqlUpdateProfile = 'update tbl_profiles set first_name = ?, last_name = ? where user_id = ?';
        $this->conn->executeUpdate($sqlUpdateProfile, array($firstName, $lastName, $id));
        $sqlUpdate = 'update tbl_users set username = ?, email = ? where id = ?';
        $this->conn->executeUpdate($sqlUpdate, array($username, $email, $id));
        return true;
    }

    public function updatePassword($username, $newPassword) {
        $dbUser = $this->loadDbUser($username);
        $sqlUpdate = 'update tbl_users set password = ? where id = ?';
        $this->conn->executeUpdate($sqlUpdate, array($newPassword, $dbUser['id']));
        return true;
    }

    public function getUserList($search, $limit = 10, $offset = 0)
    {
        $where = ' where u.username like :username or u.email like :email or p.first_name like :firstName or p.last_name like :lastName';
        $sql = 'select u.id, u.username, u.email, u.status, u.superuser, u.group_id, u.group_boss, u.client_id, u.create_at, u.lastvisit_at, p.first_name, p.last_name from tbl_users u left join tbl_profiles p on p.user_id = u.id';
        if (!empty($search)) {
            $sql .= $where;
        }
        $sql.= ' order by u.id asc LIMIT :limit OFFSET :offset';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('limit', (int) $limit, \PDO::PARAM_INT);
        $stmt->bindValue('offset', (int) $offset, \PDO::PARAM_INT);
        if (!empty($search)) {
            $stmt->bindValue('username', $search, \PDO::PARAM_STR);
            $stmt->bindValue('email', $search, \PDO::PARAM_STR);
            $stmt->bindValue('firstName', $search, \PDO::PARAM_STR);
            $stmt->bindValue('lastName', $search, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $users = $stmt->fetchAll();
        $sqlCount = 'select count(u.id) as qty from tbl_users u left join tbl_profiles p on p.user_id = u.id';
        if (!empty($search)) {
            $sqlCount .= $where;
        }
        $stmtQuantity = $this->conn->prepare($sqlCount);
        if (!empty($search)) {
            $stmtQuantity->bindValue('username', $search, \PDO::PARAM_STR);
            $stmtQuantity->bindValue('email', $search, \PDO::PARAM_STR);
            $stmtQuantity->bindValue('firstName', $search, \PDO::PARAM_STR);
            $stmtQuantity->bindValue('lastName', $search, \PDO::PARAM_STR);
        }
        $stmtQuantity->execute();
        $quantity = 0;
        $quantityRow = $stmtQuantity->fetch();
        $quantity = $quantityRow['qty'];
        return ['users' => $users, 'quantity' => $quantity];
    }

    public function doDeleteUserById($id)
    {
        $sqlDeleteAuth = 'delete from AuthAssignment where userid = ?';
        $quantity = $this->conn->executeUpdate($sqlDeleteAuth, array($id));
        $sqlDeleteProfile = 'delete from tbl_profiles where user_id = ?';
        $quantity = $this->conn->executeUpdate($sqlDeleteProfile, array($id));
        $sqlDelete = 'delete from tbl_users where id = ?';
        $quantity = $this->conn->executeUpdate($sqlDelete, array($id));
        return $quantity === 1;
    }

    public function createNewUser($username, $firstName, $lastName, $email, $password, $status, $groupId, $clientId, $groupBoss)
    {
        $sql = 'insert into tbl_users (id, username, password, email, activkey, superuser, status, group_boss, group_id, create_at, lastvisit_at, client_id) values (null, :username, :password, :email, :activkey, 0, :status, :groupBoss, :groupId, NOW(), null, :clientId)';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('username', $username, \PDO::PARAM_STR);
        $stmt->bindValue('password', md5($password), \PDO::PARAM_STR);
        $stmt->bindValue('email', $email, \PDO::PARAM_STR);
        $stmt->bindValue('activkey', md5($password.time()), \PDO::PARAM_STR);
        $stmt->bindValue('status', $status, \PDO::PARAM_INT);
        $stmt->bindValue('groupBoss', $groupBoss, \PDO::PARAM_INT);
        $stmt->bindValue('groupId', $groupId, \PDO::PARAM_INT);
        $stmt->bindValue('clientId', $clientId, \PDO::PARAM_INT);
        $stmt->execute();
        $newUserId = $this->conn->lastInsertId();
        if (empty($newUserId)) {
            throw new \Exception('Error creating the user');
        }
        $sqlProfile = 'insert into tbl_profiles (user_id, first_name, last_name) values (:userId, :firstName, :lastName)';
        $stmtProfile = $this->conn->prepare($sqlProfile);
        $stmtProfile->bindValue('userId', $newUserId, \PDO::PARAM_INT);
        $stmtProfile->bindValue('firstName', $firstName, \PDO::PARAM_STR);
        $stmtProfile->bindValue('lastName', $lastName, \PDO::PARAM_STR);
        $stmtProfile->execute();
        return $newUserId;
    }

    public function getAllPermissionTypes()
    {
        $sql = "select name, description from AuthItem where type = 0";
        $stmt = $this->conn->executeQuery($sql);
        return $stmt->fetchAll();
    }

    public function retrieveAllUserPermissions($id)
    {
        $permissionsTypes = $this->getAllPermissionTypes();
        $sql = "select itemname, data from AuthAssignment where userid = ?";
        $stmt = $this->conn->executeQuery($sql, array($id));
        $dbData = $stmt->fetchAll();
        $return = [];
        foreach ($permissionsTypes as $permissionType) {
            $data = [];
            foreach ($dbData as $datum) {
                if ($datum['itemname'] == $permissionType['name']) {
                    if (!empty($datum['data'])) {
                        $aux = unserialize($datum['data']);
                        foreach ($aux as $clientId => $clientFolder) {
                            $data[] = [
                                'id' => $clientId,
                                'folder' => $clientFolder,
                            ];
                        }
                    }
                }
            }
            $return[] = [
                'type' => $permissionType['name'],
                'data' => $data
            ];
        }
        return $return;
    }

    public function addPermissionToUser($userId, $folder, $type)
    {
        $userPermission = $this->getPermissionOfUserById($userId, $type);
        if (empty($userPermission) || !is_array($userPermission)) {
            $userPermission = [];
        }
        if (!in_array($folder, $userPermission)) {
            $userPermission[] = $folder;
            $this->updateUserPermission($userId, $type, $userPermission);
        }
        return $userPermission;
    }
    public function removePermissionOfUser($userId, $folder, $type)
    {
        $userPermission = $this->getPermissionOfUserById($userId, $type);
        if (empty($userPermission) || !is_array($userPermission)) {
            $userPermission = [];
        }
        if (in_array($folder, $userPermission)) {
            $userPermission = array_filter($userPermission, function($value) use ($folder) {
                return $value != $folder;
            });
            $this->updateUserPermission($userId, $type, $userPermission);
        }
        return $userPermission;
    }

    private function updateUserPermission($userId, $type, $userPermission)
    {
        $sqlUpdate = 'update AuthAssignment set data = ? where userid = ? and itemname = ?';
        $this->conn->executeUpdate($sqlUpdate, [serialize($userPermission), $userId, $type]);
    }


    public function getUserPermissionsList($search, $limit = 10, $offset = 0)
    {
        $where = ' where u.username like :username';
        $sqlCount = 'SELECT COUNT(*) FROM tbl_users u';
        if (!empty($search)) {
            $sqlCount .= $where;
        }
        $stmt = $this->conn->prepare($sqlCount);
        if (!empty($search)) {
            $stmt->bindValue('username', $search, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $total = $stmt->fetchColumn();

        $sqlUserRows = 'SELECT u.id, u.username FROM tbl_users u';
        if (!empty($search)) {
            $sqlUserRows .= $where;
        }
        $sqlUserRows.= ' order by u.id asc LIMIT :limit OFFSET :offset';
        $stmt = $this->conn->prepare($sqlUserRows);
        $stmt->bindValue('limit', (int) $limit, \PDO::PARAM_INT);
        $stmt->bindValue('offset', (int) $offset, \PDO::PARAM_INT);
        if (!empty($search)) {
            $stmt->bindValue('username', $search, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $usersRows = $stmt->fetchAll();
        if (!$usersRows) {
            return [
                'data' => [],
                'pagination' => [
                    'page' => $offset,
                    'limit' => $limit,
                    'total' => (int) $total,
                ],
            ];
        }
        $userIds = array_column($usersRows, 'id');
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $permissionsRows = $this->conn->fetchAll(
            "SELECT 
            aa.userid AS user_id,
            ai.name AS type,
            ai.type AS permission_type,
            ai.description
         FROM AuthAssignment aa
         INNER JOIN AuthItem ai ON ai.name = aa.itemname
         WHERE aa.userid IN ($placeholders)",
            $userIds,
            array_fill(0, count($userIds), \PDO::PARAM_INT)
        );
        $users = [];

        // Initialize users
        foreach ($usersRows as $row) {
            $users[$row['id']] = [
                'id' => (int) $row['id'],
                'username' => $row['username'],
                'permissions' => [],
            ];
        }

        // Attach permissions
        foreach ($permissionsRows as $row) {
            $userId = $row['user_id'];

            $users[$userId]['permissions'][$row['type']] = [
                'type' => $row['type'],
                'description' => $this->retrieveNameOfPermissionType($row['permission_type']), // 👈 UI name
                'name' => $row['description'],
            ];
        }

        // Normalize arrays
        foreach ($users as &$user) {
            $user['permissions'] = array_values($user['permissions']);
        }
        return [
            'data' => array_values($users),
            'pagination' => [
                'page' => $offset,
                'limit' => $limit,
                'total' => (int) $total,
            ],
        ];
    }

    private function retrieveNameOfPermissionType($type) {
        switch ($type) {
            case 2:
                return "Rol";
                break;
            default:
                return "Operacion";
                break;
        }
    }
}
