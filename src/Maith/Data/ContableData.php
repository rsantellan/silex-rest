<?php

namespace Maith\Data;

use GuzzleHttp\Client;

class ContableData
{
	private $urlPayments;
	private $urlCcte;

	public function __construct($urlPayments, $urlCcte)
	{
		$this->urlPayments = $urlPayments;
		$this->urlCcte = $urlCcte;
	}

	public function returnPayments($clientId, $month, $year)
	{
		$url = sprintf($this->urlPayments, $clientId, $month, $year);

		/** Object Way **/
		$client = new Client();
		$response = $client->get($this->urlPayments);
		if($response){
			return $response->getBody()->getContents();
        }
        return [];
		/** RAW WAY**/
        // $string = file_get_contents($url);
        // $returnData = json_decode($string);
	}

	public function returnCcte($folder, $month, $year)
	{
		$url = sprintf($this->urlCcte, $folder, $month, $year)
		/** Object Way **/
		$client = new Client();
		$response = $client->get($this->urlPayments);
		if($response){
			return $response->getBody()->getContents();
        }
        return [];
		/** RAW WAY**/
        $string = file_get_contents($url);
        $returnData = json_decode($string);
	}
}