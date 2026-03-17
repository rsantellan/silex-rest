<?php

namespace Maith\Data;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;

class ContableData
{
    private $urlPayments;
    private $urlCcte;
    private $urlAccountPerClient;
    private $token;
    private $urlPaymentByClients;
    private $urlClientExpirations;
    /**
     * @var null
     */
    private $urlPublicAvailableTasks;
    private $baseUrl;
    /**
     * @var Connection
     */
    private $conn;

    /**
     * ContableData constructor.
     * @param Connection $conn
     * @param $baseUrl
     * @param $token
     * @param $urlPayments
     * @param $urlCcte
     * @param $urlAccountPerClient
     * @param $urlPaymentByClients
     * @param $urlClientExpirations
     * @param null $urlPublicAvailableTasks
     */
    public function __construct(Connection $conn, $baseUrl, $token, $urlPayments, $urlCcte, $urlAccountPerClient, $urlPaymentByClients, $urlClientExpirations, $urlPublicAvailableTasks = null)
    {
        $this->urlPayments = $urlPayments;
        $this->urlCcte = $urlCcte;
        $this->urlAccountPerClient = $urlAccountPerClient;
        $this->token = $token;
        $this->urlPaymentByClients = $urlPaymentByClients;
        $this->urlClientExpirations = $urlClientExpirations;
        $this->urlPublicAvailableTasks = $urlPublicAvailableTasks;
        $this->baseUrl = $baseUrl;
        $this->conn = $conn;
    }

    /**
     * @param $clientId
     * @param $month
     * @param $year
     * @return array|mixed
     */
    public function returnPayments($clientId, $month, $year)
    {
        $url = sprintf($this->urlPayments, $clientId, $month, $year);
        $client = new Client();
        $response = $client->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
        ]);
        if ($response) {
            return $this->formatPayment($month, json_decode($response->getBody()->getContents(), true));
        }
        return ['data' => []];
    }

    public function returnPaymentsByClients($clients, $month, $year)
    {
        $url = $this->baseUrl. '/localhost/payments-per-clients';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => [
                'clients' => $clients,
                'month' => $month,
                'year' => $year,
            ]
        ]);
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return ['data' => []];
    }

    private function formatPayment($month, $response)
    {
        //var_dump($response);
        $month = (int) $month;
        $returnData = [];
        $months = [
            1=>"Enero",
            2=>"Febrero",
            3=>"Marzo",
            4=>"Abril",
            5=>"Mayo",
            6=>"Junio",
            7=>"Julio",
            8=>"Agosto",
            9=>"Septiembre",
            10=>"Octubre",
            11=>"Noviembre",
            12=>"Diciembre"
        ];
        if (isset($response['isvalid'])) {
            $returnData['isvalid'] = $response['isvalid'];
        } else {
            $returnData['isvalid'] = false;
        }
        if ($returnData['isvalid']) {
            $returnData['data'] = [];
            foreach ($response['data'] as $clientId => $clientData) {
                $clientReturn = ['calendar' => [], 'client' => []];
                if (isset($clientData['calendar'])) {
                    foreach ($clientData['calendar'] as $calendarId => $calendarData) {
                        $calendarData['day'] = $calendarData['month'];
                        if ($calendarData['day']) {
							$calendarData['month'] = sprintf('%s de %s', $calendarData['day'], $months[$month]);
                        }
                        $payments = [];
                        foreach ($calendarData['payments'] as $payment) {
                            $taxes = [];
                            foreach ($payment['taxes'] as $tax) {
                                $tax['amount'] = number_format($tax['amount'], 0, ',', '.');
                                $taxes[] = $tax;
                            }
                   	    $payment['taxes'] = $taxes;
			    if (!empty($taxes))
                            	$payments[] = $payment;
				
                        }
                        $calendarData['payments'] = $payments;
                        $clientReturn['calendar'][] = $calendarData;
                    }
                    usort($clientReturn['calendar'], [$this, "compareCalendarData"]);
                }
                if (isset($clientData['client'])) {
                    $clientReturn['client'] = $clientData['client'];
                }
                $returnData['data'][$clientId] = $clientReturn;
            }
        }
        return $returnData;
    }

    /**
     * @param $calendarDataA
     * @param $calendarDataB
     * @return int
     */
    private function compareCalendarData($calendarDataA, $calendarDataB) {
        if (!$calendarDataA['day'] && !$calendarDataB['day']) {
            return 0;
        }
        if ($calendarDataA['day'] && !$calendarDataB['day']) {
            return 1;
        }
        if (!$calendarDataA['day'] && $calendarDataB['day']) {
            return -1;
        }
        return ($calendarDataA['day'] < $calendarDataB['day']) ? -1 : 1;
    }

    /**
     * @param $folder
     * @param $month
     * @param $year
     * @return array
     */
    public function returnCcte($folder, $month, $year)
    {
        $url = sprintf($this->urlCcte, $folder, $month, $year). '?XDEBUG_SESSION_START=PHPSTORM';
        /** Test way **/
        //return $this->formatCcteResponse(json_decode($this->testCcte(), true));
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
        ]);
        if ($response) {
            return $this->formatCcteResponse(json_decode($response->getBody()->getContents(), true));
        }
        return [];
    }

    /**
     * @param $response
     * @return array
     */
    private function formatCcteResponse($response)
    {
        $returnData = [];
        if (isset($response['isvalid'])) {
            $returnData['isvalid'] = $response['isvalid'];
        } else {
            $returnData['isvalid'] = true;
        }
        if ($returnData['isvalid']) {
            $returnData['data'] = [];
            if (isset ($response['data'])) {
                if (isset($response['data']['Clientes'])) {
                    $returnData['data'] = ['Clientes' => [], 'Grupo' => [], 'TotalGrupo' => [] ] ;
                    foreach ($response['data']['Clientes'] as $clientName => $clientData) {
                        $returnData['data']['Clientes'][$clientName] = ['Cuentas' => [], 'SubtotalCliente' => []];
                        if (isset ($clientData['Cuentas'])) {
                            foreach ($clientData['Cuentas'] as $cuentaType => $cuentaData) {
                                if (substr_count($cuentaType, ' USD') == 0) {
                                    $returnData['data']['Clientes'][$clientName]['Cuentas'][$cuentaType] = ['Movimientos' => [], 'SaldoInicial' => [], 'SaldoFinal' => []];
                                    if (isset ($clientData['Cuentas'][$cuentaType]['SaldoInicial'])) {
                                        $returnData['data']['Clientes'][$clientName]['Cuentas'][$cuentaType]['SaldoInicial']['SaldoPesos'] = number_format($clientData['Cuentas'][$cuentaType]['SaldoInicial']['SaldoPesos'], 0, ',', '.');
                                    }
                                    if (isset ($clientData['Cuentas'][$cuentaType]['SaldoFinal'])) {
                                        $returnData['data']['Clientes'][$clientName]['Cuentas'][$cuentaType]['SaldoFinal']['SaldoPesos'] = number_format($clientData['Cuentas'][$cuentaType]['SaldoFinal']['SaldoPesos'], 0, ',', '.');
                                    }
                                    if (isset ($clientData['Cuentas'][$cuentaType]['Movimientos'])) {
                                        foreach ($clientData['Cuentas'][$cuentaType]['Movimientos'] as $movimientoData) {
                                            $fecha = \DateTime::createFromFormat('M j Y', substr($movimientoData['FECHA'],0,11));
                                            $showDocument = $this->parseDocumentName($movimientoData['Documento'], $cuentaType);


                                            $movimiento = [
                                                'AcumuladoPesos' => number_format(round($movimientoData['AcumuladoPesos']), 0, ',', '.'),
                                                'Cliente' => $movimientoData['Cliente'],
                                                'Documento' => $showDocument,
                                                'FECHA' => $fecha->format('d/m/y'),
                                                'SaldoPesos' => number_format($movimientoData['SaldoPesos'], 0, ',', '.'),
                                                'TipoCliente' => $movimientoData['TipoCliente'],
                                                'TipoDoc' => $movimientoData['TipoDoc'],
                                                'UnidadNegocios' => $movimientoData['UnidadNegocios'],
                                            ];
                                            $returnData['data']['Clientes'][$clientName]['Cuentas'][$cuentaType]['Movimientos'][] = $movimiento;
                                        }
                                    }
                                }
                            }
                        }
                        if (isset ($clientData['SubtotalCliente'])) {
                            $returnData['data']['Clientes'][$clientName]['SubtotalCliente']['SaldoPesos'] = number_format($clientData['SubtotalCliente']['SaldoPesos'], 0, ',', '.');
                        }
                    }
                }
                if (isset($response['data']['Grupo'])) {
                    $returnData['data']['Grupo'] = $response['data']['Grupo'];
                }
                if (isset($response['data']['TotalGrupo'])) {
                    $returnData['data']['TotalGrupo']['SaldoPesos'] = number_format($response['data']['TotalGrupo']['SaldoPesos'], 0, ',', '.');
                }
            }
        }
        return $returnData;
    }

    /**
     * @param $document
     * @param $accountType
     * @return string
     */
    private function parseDocumentName($document, $accountType)
    {
        $showDocument = $document;
        if (substr_count($showDocument, 'E-Factura.') > 0 && substr_count($accountType,'HONORARIOS') > 0) {
            return $document;
        }
        if (substr_count($showDocument, 'E-Ticket.') > 0) {
            // E-Ticket. n\u00b0: 2535 BPS Generico WEB
            $showDocument = substr($showDocument, 20);
        } else {
            $splitedDocumento = explode('-', $document);
            if (substr_count($document, 'Pago de Terceros') > 0) {
                if (isset($splitedDocumento[2]) && isset($splitedDocumento[3])) {
                    if (trim($splitedDocumento[2]) == trim($splitedDocumento[3])) {
                        unset($splitedDocumento[3]);
                    }
                }
                unset($splitedDocumento[0]);
                $showDocument = trim(implode('-', $splitedDocumento));
            } else {
                $showDocument = $splitedDocumento[0];
            }
            if (substr_count($showDocument, 'Pago a Terceros : ') > 0) {
                $showDocument = str_replace('Pago a Terceros : ', '', $showDocument);
            }
            if (substr_count($showDocument, 'Recibos de Cobranza') > 0) {
                $showDocument = 'Recibos';
            }
            $showDocument = trim($showDocument);
            if (substr_count($showDocument, 'Cambio de Saldos') > 0) {
                $showDocument = 'Cambio de Saldos';
            }
            if (substr_count($showDocument, 'eFactura') > 0) {
                $showDocument = 'eFactura '. substr($showDocument, -5, 5);
            }
        }
        return $showDocument;
    }

    public function returnAccountsPerClients($clients, $month, $year)
    {
        $fullData = ['Clients' => []];
        $totals = ['SaldoPesos' => 0, 'SaldoDolares' => 0];
        foreach ($clients as $clientId) {
            $data = $this->doCallClientAccountData($clientId, $month, $year);
            $parsed = $this->parseAccountsPerClientResponse($fullData, $totals, $data);
            $fullData = $parsed['fullData'];
            $totals = $parsed['totals'];
        }
        $fullData['totals'] = $totals;
        return $fullData;
    }

    private function parseAccountsPerClientResponse($fullData, $totals, $data) {
        if (array_key_exists('isvalid', $data) && $data['isvalid']) {
            foreach ($data['data']['Clientes'] as $clientKey => $values) {
                if (array_key_exists('SaldoPesos', $values['SubtotalCliente'])) {
                    $totals['SaldoPesos'] = $totals['SaldoPesos'] + $values['SubtotalCliente']['SaldoPesos'];
                }
                if (array_key_exists('SaldoDolares', $values['SubtotalCliente'])) {
                    $totals['SaldoDolares'] = $totals['SaldoDolares'] + $values['SubtotalCliente']['SaldoDolares'];
                }
                $fullData['Clients'][$clientKey] = $values;
            }
        }
        return [
            'totals' => $totals,
            'fullData' => $fullData,
        ];
    }
    private function doCallClientAccountData($clientId, $month, $year)
    {
        $url = sprintf($this->urlAccountPerClient, $clientId, $month, $year);
        //var_dump($url);
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }


    public function returnPaymentsPerClients($clients, $month, $year)
    {
        $url = $this->urlPaymentByClients;
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => [
                'clientIds' => $clients,
                'month' => $month,
                'year' => $year,
            ]
        ]);
        if ($response) {
            //$data = json_decode($response->getBody()->getContents(), true);
            return $this->formatPayment($month, json_decode($response->getBody()->getContents(), true));
        }
        return ['data' => []];
    }

    public function returnClientExpirations($clientId)
    {
        $url = sprintf($this->urlClientExpirations, $clientId);
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
        ]);
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }


    public function returnPublicAvailableTasks()
    {
        $url = $this->urlPublicAvailableTasks;
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
        ]);
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return [];
    }

    /**
     * @param $id
     * @param $fileId
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function retrieveTaskFile($id, $fileId)
    {
        $url = $this->baseUrl . sprintf('/public/tasks/%s/%s/retrieve-created-task-file', $id, $fileId);
        $client = new Client();
        return $client->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            'stream' => true
        ]);
    }

    public function createPublicTask($folder, $createdBy, $taskId, $comment)
    {
        $url = sprintf($this->baseUrl. '/public/tasks/%s/create-to-client', $taskId);
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => [
                'folder' => $folder,
                'createdBy' => $createdBy,
                'comment' => $comment
            ]
        ]);
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return ['data' => []];
    }
    public function showUserPublicTask($all, $user)
    {
        $url = $this->baseUrl. '/public/tasks/retrieve-created-tasks';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => [
                'all' => $all,
                'user' => $user,
            ]
        ]);
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return ['data' => []];
    }

    public function retrieveAllGroups()
    {
        $sql = 'select id, name, code from ec_client_groups order by name';
        $stmt = $this->conn->executeQuery($sql, []);
        return $stmt->fetchAll();
    }
    public function retrieveAlClients($groupId = null, $clientId = null)
    {
        $params = [];
        $where = '';
        if ($groupId) {
            $params = [$groupId];
            $where = ' where g.id = ?';
        } else {
            if ($clientId) {
                $params = [$clientId];
                $where = ' where c.id = ?';
            }
        }

        $sql = sprintf('select c.id, c.social_reason, c.folder_number, g.id as groupId, g.name as groupName, g.code as groupCode from ec_clients c left outer join ec_client_groups g on g.id = c.id_group %s order by c.social_reason', $where);
        $stmt = $this->conn->executeQuery($sql, $params);
        return $stmt->fetchAll();
    }

    public function addCommentToTask($createdBy, $taskId, $comment)
    {
        $url = sprintf($this->baseUrl. '/public/tasks/%s/add-comment-to-task', $taskId);
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => [
                'user' => $createdBy,
                'comment' => $comment
            ]
        ]);
        if ($response) {
            return json_decode($response->getBody()->getContents(), true);
        }
        return ['data' => []];
    }

    public function returnCctePerClientsPerRange($clients, $dateFrom, $dateTo)
    {
        $url = $this->baseUrl. '/localhost/accounts-per-client/range-data.html';
        /** Object Way **/
        $client = new Client();
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
            ],
            \GuzzleHttp\RequestOptions::JSON => [
                'clients' => $clients,
                'from' => $dateFrom,
                'to' => $dateTo,
            ]
        ]);
        if ($response) {
            $data = json_decode($response->getBody()->getContents(), true);
            $fullData = ['Clients' => []];
            $totals = ['SaldoPesos' => 0, 'SaldoDolares' => 0];
            $return = [];
            if ($data['isvalid']) {
                foreach ($data['data'] as $clientId => $clientData) {
                    $parsed = $this->parseAccountsPerClientResponse($fullData, $totals, $clientData);
                    $fullData = $parsed['fullData'];
                    $totals = $parsed['totals'];
                    $return[$clientId] = $fullData;
                }
                $fullData['totals'] = $totals;
                return $fullData;
            }
            return $return;
        }
        return ['data' => []];
    }

}
