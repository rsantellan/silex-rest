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
    const GET_FILE = '/localhost/%s/file/%s';
    const DGI_QR = '/localhost/%s/dgi-qr';
    const CONTACT_DATA = '/localhost/contact-info';
    const PAYMENT_FILE = '/localhost/upload-payment-file?XDEBUG_SESSION_START=PHPSTORM';
    const SAVE_CONTACT = '/localhost/create-contact-by-app';
    const PAYMENTS_DATA = '/localhost/%s/payment-summary';

    private $baseUrl;

    /**
     * ClientData constructor.
     * @param $baseUrl
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @return mixed|string
     */
    public function getContactData()
    {
        /** Object Way **/
        $client = new Client();
        $response = $client->get($this->baseUrl.self::CONTACT_DATA);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return $this->parseGetContactInfoResponse(json_decode($response->getBody()->getContents(), true));
            }
        }
        return '';
    }

    /**
     * @param $clientId
     * @return array
     */
    public function getFiles($clientId)
    {
        $url = sprintf($this->baseUrl.self::FILES, $clientId);
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return $this->parseGetFilesResponse(json_decode($response->getBody()->getContents(), true));
            }
        }
        return [];
    }

    public function getFile($clientId, $id)
    {
        $url = sprintf($this->baseUrl.self::GET_FILE, $clientId, $id);
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody()->getContents(), true);
                if ($data['isvalid']) {
                    return $data['file'];
                }
            }
        }
        return null;
    }

    /**
     * @param $clientId
     * @param bool $debug
     * @return array
     */
    public function getDgiQr($clientId, $debug = false)
    {
        $url = sprintf($this->baseUrl.self::DGI_QR, $clientId);
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return $this->parseGetDgiQrResponse(json_decode($response->getBody()->getContents(), true), $clientId, $debug);
            } else {
                if ($debug) {

                }
            }
        }
        return [];
    }

    /**
     * @param $clientId
     * @return array
     */
    public function getCalendarPaymentData($clientId)
    {
        $url = sprintf($this->baseUrl.self::PAYMENTS_DATA, $clientId);
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return $this->parseGetCalendarPaymentDataResponse(json_decode($response->getBody()->getContents(), true));
            }
        }
        return [];
    }

    /**
     * @param $name
     * @param $email
     * @param $phone
     * @param $comment
     * @return array|mixed
     */
    public function saveNewContact($name, $email, $phone, $comment)
    {
        $url = $this->baseUrl.self::SAVE_CONTACT;
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            \GuzzleHttp\RequestOptions::JSON => [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'comment' => $comment,
            ]
        ]);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        }
        return [
            'isvalid' => false,
            'message' => 'Ocurrio un error',
        ];
    }

    /**
     * @param $email
     * @param $text
     * @param $amount
     * @param $uploadedFileName
     * @param $fileName
     * @param $filePath
     * @return array|mixed
     */
    public function sendPaymentFile($email, $text, $amount, $uploadedFileName, $fileName, $filePath)
    {
        $url = $this->baseUrl.self::PAYMENT_FILE;
        $data = [
            'text' => $text,
            'amount' => $amount,
            'uploadedFileName' => $uploadedFileName,
            'fileName' => $fileName,
            'filePath' => $filePath,
            'email' => $email,
        ];
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            \GuzzleHttp\RequestOptions::JSON => $data,
        ]);
        if ($response) {
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
        }
        return [
            'isvalid' => false,
            'message' => 'Ocurrio un error',
        ];
    }

    /**
     * @param $response
     * @return array
     */
    public function parseGetCalendarPaymentDataResponse($response)
    {
        if ($response && $response['isvalid']) {
            $aux =  [
                'name' => $response['name'],
                'calendars' => []
            ];
            foreach($response['payments'] as $key => $value) {
                $aux['calendars'][] = [
                    'name' => $key,
                    'due_dates' => $value,
                ];
            }
            return $aux;
        }
        return [];
    }
    /**
     * @param $response
     * @return array
     */
    private function parseGetFilesResponse($response)
    {
        if ($response && $response['isvalid']) {
            return [
                'name' => $response['data']['name'],
                'files' => $response['data']['files'],
            ];
        }
        return [];
    }

    /**
     * @param $response
     * @param $clientId
     * @param $debug
     * @return array
     */
    private function parseGetDgiQrResponse($response, $clientId, $debug)
    {
        if ($response && $response['isvalid']) {
            if (!empty($response['data']['name']) && !$debug) {
                $data = [
                    'name' => $response['data']['name'],
                    'url' => $response['data']['url'],
                ];
                if ($debug) {
                    $data['clientId'] = $clientId;
                }
                return $data;
            }
        }
        return [];
    }

    /**
     * @param $response
     * @return mixed|string
     */
    private function parseGetContactInfoResponse($response) {
        if ($response && $response['isvalid']) {
            return $response['html'];
        }
        return '';
    }

}