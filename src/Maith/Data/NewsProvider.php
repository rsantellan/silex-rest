<?php

namespace Maith\Data;

use Doctrine\DBAL\Connection;

class NewsProvider
{
	private $conn;

	public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function retrieveLastNews($limit = 5)
    {
    	$limit = (int) $limit;
    	if($limit == 0){
    		$limit = 5;
    	}
    	$sql = 'select id, title, content, author, timestamp from ec_blog where private=0 order by id desc limit '.$limit;
    	$stmt = $this->conn->executeQuery($sql, array());
        return $stmt->fetchAll();
    }
}