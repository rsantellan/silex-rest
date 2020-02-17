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
	$filename = '/tmp/push'.time();
	file_put_contents($filename, json_encode($params));
		$client = new Client(['base_uri' => $this->host]);
	    try {
            $response = $client->post($this->url, [
                'json' => $params,
                'debug' => false,
            ]);
            if($response){
		file_put_contents($filename, json_encode($response->getBody()->getContents()));
                return $response->getBody()->getContents();
            }
        }catch (\Exception $e) {
            throw $e;
        }
		return null;
    }

}
