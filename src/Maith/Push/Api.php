<?php

namespace Maith\Push;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;

class Api
{
	
	private $secret;
	private $host;
	private $url;
    /**
     * @var Connection
     */
    private $conn;

    public function __construct(Connection $conn, $host, $url, $secret)
    {
        $this->host = $host;
        $this->url = $url;
        $this->secret = $secret;
        $this->conn = $conn;
    }

    public function doPush($title, $body, $users = [])
    {
	$sendUsers = [];
	foreach($users as $user)
	{
	  $sendUsers[] = $user;
	}
    	$params = [
    		'key' => $this->secret,
    		'users' => $sendUsers,
    		'title' => $title,
    		'body' => $body
    	];
	    $filename = '/tmp/push'.time();
	    $encoded_params = json_encode($params);
		$client = new Client(['base_uri' => $this->host]);
		$responseCode = 500;
	    try {
            $response = $client->post($this->url, [
                'json' => $params,
                'debug' => false,
                'http_errors' => false
            ]);           
            //var_dump($response);
            if($response){
                $responseCode = $response->getStatusCode();
                $responseContent = $response->getBody()->getContents();
                $this->savePushData($responseCode, $params, $responseContent);
                return $responseContent;
            } else {
                $this->savePushData($responseCode, $params, []);
            }
        }catch (\Exception $e) {
	    $this->savePushData($responseCode, $params, "[".$e->getCode()."]".$e->getMessage());
            throw $e;
        }
		return null;
    }

    private function savePushData($responseCode, $params, $responseData) {
        $sql = 'insert into mobile_push_data (response_code, request_params, response_data) values (?, ?, ?)';
        $this->conn->executeUpdate($sql, array($responseCode, json_encode($params), json_encode($responseData)));
    }

}
