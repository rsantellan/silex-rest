<?php
/**
 * @author    Rodrigo Santellan <rsantellan@gmail.com.uy>
 */

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class MonthAmountTest extends TestCase
{

    public function testMonthAmounts()
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
        $postParams = ["year" => date('Y'), 'month' => date('n')];
        $queryPost = ['headers' => $headers, 'json' => $postParams];
        $responseNews = $guzzle->post( $_ENV['base.url.path']. '/api/month-amount', $queryPost);
        if($responseNews->getStatusCode() !== 200) throw new \Exception($responseNews->getStatusCode());
        $responseData = json_decode($responseNews->getBody()->getContents());
        $this->assertTrue($responseData->isvalid);
    }
}
