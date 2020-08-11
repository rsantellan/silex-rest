<?php
/**
 * @copyright Copyright © 2020 Geocom. All rights reserved.
 * @author    Rodrigo Santellan <rsantellan@geocom.com.uy>
 */

namespace Maith\Data;

use GuzzleHttp\Client;

class ClientData
{
    const FILES = '/localhost/%s/files';

    private $baseUrl;

    /**
     * ClientData constructor.
     * @param $baseUrl
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function getFiles($clientId)
    {
        $url = sprintf($this->baseUrl.self::FILES, $clientId);
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url);
        if ($response) {
            return $this->parseGetFilesResponse(json_decode($response->getBody()->getContents(), true));
        }
        return [];
    }

    private function parseGetFilesResponse($response)
    {
        return $response;
    }

}