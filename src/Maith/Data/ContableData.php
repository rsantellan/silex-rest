<?php

namespace Maith\Data;

use GuzzleHttp\Client;

class ContableData
{
    private $urlPayments;
    private $urlCcte;

    /**
     * ContableData constructor.
     * @param $urlPayments
     * @param $urlCcte
     */
    public function __construct($urlPayments, $urlCcte)
    {
        $this->urlPayments = $urlPayments;
        $this->urlCcte = $urlCcte;
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
        // This is for testing
        //return $this->formatPayment($month, json_decode($this->testPayment(), true));
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url);
        if ($response) {
            return $this->formatPayment($month, json_decode($response->getBody()->getContents(), true));
        }
        return ['data' => []];
        /** RAW WAY**/
        // $string = file_get_contents($url);
        // $returnData = json_decode($string);
    }

    private function formatPayment($month, $response)
    {
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
                        $clientReturn['calendar'][$calendarId] = $calendarData;
                    }
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
     * @param $folder
     * @param $month
     * @param $year
     * @return array
     */
    public function returnCcte($folder, $month, $year)
    {
        $url = sprintf($this->urlCcte, $folder, $month, $year);
        /** Test way **/
        //return $this->formatCcteResponse(json_decode($this->testCcte(), true));
        /** Object Way **/
        $client = new Client();
        $response = $client->get($url);
        if ($response) {
            return $this->formatCcteResponse(json_decode($response->getBody()->getContents(), true));
        }
        return [];
        /** RAW WAY**/
        $string = file_get_contents($url);
        $returnData = json_decode($string);
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
            $returnData['isvalid'] = false;
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
                                        $movimiento = [
                                            'AcumuladoPesos' => round($movimientoData['AcumuladoPesos']),
                                            'Cliente' => $movimientoData['Cliente'],
                                            'Documento' => $movimientoData['Documento'],
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
     * @return string
     */
    private function testPayment()
    {
        return '{
    "data": {
        "216": {
            "calendar": {
                "4196": {
                    "id": 3,
                    "month": 17,
                    "name": "BPS Calendario Gen\u00e9rico",
                    "payments": [
                        {
                            "amountWithTaxes": 478718,
                            "createdat": "07/06/2019",
                            "notconfirmed": true,
                            "notes": "debe ademas IRPF de 4 meses mas sus multas y recargos",
                            "notified": false,
                            "notpayment": false,
                            "paid": false,
                            "reviewed": true,
                            "taxes": [
                                {
                                    "amount": 478718,
                                    "name": "BPS Aportes"
                                }
                            ],
                            "updatedat": "07/06/2019",
                            "whopays": 0
                        }
                    ],
                    "review": true,
                    "taxes": true
                },
                "4198": {
                    "id": 16,
                    "month": 24,
                    "name": "DGI Cede",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "4201": {
                    "id": 56,
                    "month": 30,
                    "name": "BSE - Seguros Accidentes",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "5373": {
                    "id": 77,
                    "month": 24,
                    "name": "BPS WEB",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "5643": {
                    "id": 35,
                    "month": 27,
                    "name": "DGI Facilidades",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "5694": {
                    "id": 35,
                    "month": 19,
                    "name": "DGI Facilidades",
                    "payments": [],
                    "review": false,
                    "taxes": true
                }
            },
            "client": {
                "carpeta": "1370",
                "id": 216,
                "razonsocial": "TILSIT S.A."
            }
        },
        "34": {
            "calendar": {
                "3919": {
                    "id": 1,
                    "month": 25,
                    "name": "DGI Codeco",
                    "payments": [],
                    "review": false,
                    "taxes": true
                }
            },
            "client": {
                "carpeta": "704",
                "id": 34,
                "razonsocial": "KAMIKI S.A."
            }
        },
        "5": {
            "calendar": {
                "3898": {
                    "id": 34,
                    "month": 24,
                    "name": "DGI IRPF e IRNR Cat.1 y 2",
                    "payments": [],
                    "review": false,
                    "taxes": true
                }
            },
            "client": {
                "carpeta": "434",
                "id": 5,
                "razonsocial": "SAGASTUME CAVELLI Sonia Rene"
            }
        },
        "518": {
            "calendar": {
                "4720": {
                    "id": 3,
                    "month": 17,
                    "name": "BPS Calendario Gen\u00e9rico",
                    "payments": [
                        {
                            "amountWithTaxes": 114035,
                            "createdat": "05/06/2019",
                            "notconfirmed": true,
                            "notes": null,
                            "notified": true,
                            "notpayment": false,
                            "paid": false,
                            "reviewed": true,
                            "taxes": [
                                {
                                    "amount": 114035,
                                    "name": "BPS Aportes"
                                }
                            ],
                            "updatedat": "05/06/2019",
                            "whopays": 0
                        }
                    ],
                    "review": true,
                    "taxes": true
                },
                "4727": {
                    "id": 50,
                    "month": 30,
                    "name": "CJPPU - Caja Profesional",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "4729": {
                    "id": 59,
                    "month": 24,
                    "name": "BPS - Fonasa Serv. Personales",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "5310": {
                    "id": 52,
                    "month": 24,
                    "name": "BPS Servicio Dom\u00e9stico",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "5336": {
                    "id": 56,
                    "month": 30,
                    "name": "BSE - Seguros Accidentes",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "5687": {
                    "id": 1,
                    "month": 25,
                    "name": "DGI Codeco",
                    "payments": [],
                    "review": false,
                    "taxes": true
                }
            },
            "client": {
                "carpeta": "3003",
                "id": 518,
                "razonsocial": "DE LEON SAGASTUME RICARDO"
            }
        },
        "738": {
            "calendar": {
                "5635": {
                    "id": 1,
                    "month": 25,
                    "name": "DGI Codeco",
                    "payments": [],
                    "review": false,
                    "taxes": true
                },
                "5645": {
                    "id": 3,
                    "month": 17,
                    "name": "BPS Calendario Gen\u00e9rico",
                    "payments": [],
                    "review": false,
                    "taxes": true
                }
            },
            "client": {
                "carpeta": "1883",
                "id": 738,
                "razonsocial": "ORGANICO.UY SRL"
            }
        }
    },
    "isvalid": true
}
';
    }

    /**
     * @return string
     */
    private function testCcte()
    {
        return '{
    "data": {
        "Clientes": {
            "0101883 - ORGANICO.UY  S.R.L.": {
                "Cuentas": {
                    "02 - Impuestos y Gastos": {
                        "Movimientos": [
                            {
                                "AcumuladoDolares": 309.34,
                                "AcumuladoPesos": -2498.1300006001,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Pago de Terceros 000000054938 - Pago a Terceros : VARIOS por CLIENTE - Santander Cuota 18 - Santander Cuota 18",
                                "FECHA": "Jul  4 2019 12:00:00:000AM",
                                "SaldoDolares": 782.27,
                                "SaldoPesos": 0,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": 309.34,
                                "AcumuladoPesos": -6.0012553149136e-07,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Pago de Terceros 000000055149 - Pago a Terceros : VARIOS por CLIENTE - cbio ps a dls - cbio ps a dls",
                                "FECHA": "Jul  4 2019 12:00:00:000AM",
                                "SaldoDolares": 0,
                                "SaldoPesos": 2498.13,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": 238,
                                "AcumuladoPesos": -6.0012553149136e-07,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Recibos de Cobranza 000200530221 - R.G.: 000500011041 - Obs.: cbio ps a dls - R.G.: 000500011041 - Obs.: cbio ps a dls",
                                "FECHA": "Jul  4 2019 12:00:00:000AM",
                                "SaldoDolares": -71.34,
                                "SaldoPesos": 0,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": 238,
                                "AcumuladoPesos": -16296.0000006,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Recibos de Cobranza 000200530217 - R.G.: 000500011037 - Obs.: transf. brou - R.G.: 000500011037 - Obs.: transf. brou",
                                "FECHA": "Jul 24 2019 12:00:00:000AM",
                                "SaldoDolares": 0,
                                "SaldoPesos": -16296,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": 238,
                                "AcumuladoPesos": -6.0012462199666e-07,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Pago de Terceros 000000055147 - Pago a Terceros : VARIOS por CLIENTE - GSoft - GSoft",
                                "FECHA": "Jul 24 2019 12:00:00:000AM",
                                "SaldoDolares": 0,
                                "SaldoPesos": 16296,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": 238,
                                "AcumuladoPesos": -5900.0000006001,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Recibos de Cobranza 000200530220 - R.G.: 000500011040 - Obs.: transf. brou - R.G.: 000500011040 - Obs.: transf. brou",
                                "FECHA": "Jul 24 2019 12:00:00:000AM",
                                "SaldoDolares": 0,
                                "SaldoPesos": -5900,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": 238,
                                "AcumuladoPesos": -6.0012462199666e-07,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Pago de Terceros 000000055148 - Pago a Terceros : VARIOS por CLIENTE - New Age Data - New Age Data",
                                "FECHA": "Jul 24 2019 12:00:00:000AM",
                                "SaldoDolares": 0,
                                "SaldoPesos": 5900,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": -762,
                                "AcumuladoPesos": -6.0012462199666e-07,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Recibos de Cobranza 000200530317 - R.G.: 000500011115 - Obs.: deposito 23 - R.G.: 000500011115 - Obs.: deposito 23",
                                "FECHA": "Aug 12 2019 12:00:00:000AM",
                                "SaldoDolares": -1000,
                                "SaldoPesos": 0,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            },
                            {
                                "AcumuladoDolares": -762,
                                "AcumuladoPesos": 2146.9999993999,
                                "Cliente": "0101883 - ORGANICO.UY  S.R.L.",
                                "Documento": "Pago de Terceros 000000055472 - Pago a Terceros : DGI -  - ",
                                "FECHA": "Aug 26 2019 12:00:00:000AM",
                                "SaldoDolares": 0,
                                "SaldoPesos": 2147,
                                "TipoCliente": "De Leon Ricardo",
                                "TipoDoc": "02 - Impuestos y Gastos",
                                "UnidadNegocios": "Maldonado"
                            }
                        ],
                        "SaldoFinal": {
                            "SaldoDolares": -762,
                            "SaldoPesos": 2146.9999993999
                        },
                        "SaldoInicial": {
                            "SaldoDolares": -472.93,
                            "SaldoPesos": -2498.1300006001
                        }
                    },
                    "04 - Pendientes": {
                        "Movimientos": [],
                        "SaldoFinal": {
                            "SaldoDolares": 0,
                            "SaldoPesos": 0
                        },
                        "SaldoInicial": {
                            "SaldoDolares": 0,
                            "SaldoPesos": 0
                        }
                    }
                },
                "SubtotalCliente": {
                    "SaldoDolares": -762,
                    "SaldoPesos": 2146.9999993999
                }
            }
        },
        "Grupo": "Totales",
        "TotalGrupo": {
            "SaldoDolares": -762,
            "SaldoPesos": 2146.9999993999
        }
    },
    "isvalid": true
}
';
    }
}
