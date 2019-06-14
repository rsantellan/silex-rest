<?php
/**
 * @author    Rodrigo Santellan <rsantellan@gmail.com.uy>
 */

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class NewsTest extends TestCase
{

    public function testGetNews()
    {
        // /rest-api/index.php/
        $data = ['json' => ['_username' => $_ENV['user.username'], '_password' => $_ENV['user.password']]];
        //$data = ['json' => ['document' => $user, 'password' => $password]];
        $guzzle = new GuzzleHttp\Client(['base_uri' => $_ENV['base.url']]);
        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $guzzle->post( $_ENV['base.url.path']. '/api/login', $data);
        if($response->getStatusCode() !== 200) throw new \Exception($response->getStatusCode());
        $loginData = json_decode($response->getBody()->getContents());
        $token = $loginData->token;
        $this->assertTrue(strlen($token) > 0);
        $headers = [];
        $headers['X-Access-Token'] = "Bearer " . $token;
        $newsData = ['headers' => $headers];
        $responseNews = $guzzle->get( $_ENV['base.url.path']. '/api/news', $newsData);
        if($responseNews->getStatusCode() !== 200) throw new \Exception($responseNews->getStatusCode());
        $newsData = json_decode($responseNews->getBody()->getContents());
        $this->assertTrue($newsData->sucess);
    }
}
