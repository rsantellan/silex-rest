<?php
/**
 * @author    Rodrigo Santellan <rsantellan@gmail.com.uy>
 */

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class CurrentAccountTest extends TestCase
{

    public function testCurrentAccounts()
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
        $clientDataResponse = $guzzle->get($_ENV['base.url.path'] . '/api/', ['headers' => $headers]);
        if($clientDataResponse->getStatusCode() !== 200) throw new \Exception($clientDataResponse->getStatusCode());
        $clientData = json_decode($clientDataResponse->getBody()->getContents());
        $this->assertTrue($clientData->success);
        foreach ($clientData->clients as $client) {
            $postParams = ["year" => date('Y'), 'month' => date('n'), 'folder' => $client->folder_number];
            try{
                $responseAccount = $guzzle->post( $_ENV['base.url.path']. '/api/current-account-data', ['headers' => $headers, 'json' => $postParams]);
                if($responseAccount->getStatusCode() !== 200) {
                    var_dump($responseAccount->getStatusCode());
                    var_dump(['headers' => $headers, 'json' => $postParams]);
                } else {
                    $accountData = json_decode($responseAccount->getBody()->getContents());
                    $this->assertTrue($accountData->isvalid);
                }
            }catch (\Exception $e) {
                var_dump($client);
                var_dump($e->getMessage());
                //var_dump(['headers' => $headers, 'json' => $postParams]);
            }

        }
    }
}
