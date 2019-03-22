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
    	$params = [
    		'key' => $this->secret,
    		'users' => $users,
    		'title' => $title,
    		'body' => $body
    	];
		$client = new Client('base_uri' => $this->host);

		$response = $client->post($this->url, [
		    'json' => $params
		]);
		if($response){
			return $response->getBody()->getContents();
        }
		return null;
    }

}