<?php

namespace Maith\Push;

use GuzzleHttp\Client;

class Api
{
	
	private $secret;
	private $host;
	private $url;

	public function __construct($host, $url, $secret)
    {
        $this->host = $host;
        $this->url = $url;
        $this->secret = $secret;
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
		$client = new Client(['base_uri' => $this->host]);
	var_dump($params);
	var_dump(json_encode($params));
		$response = $client->post($this->url, [
		    'json' => $params,
		    'debug' => true,
		]);
var_dump($response);
		if($response){
			return $response->getBody()->getContents();
        }
		return null;
    }

}
