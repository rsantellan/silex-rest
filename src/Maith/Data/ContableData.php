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
		// This is for testing
		//return json_decode($this->testPayment());
		/** Object Way **/
		$client = new Client();
		$response = $client->get($url);
		if($response){
            return json_decode($response->getBody()->getContents());
        }
        return [];
		/** RAW WAY**/
        // $string = file_get_contents($url);
        // $returnData = json_decode($string);
	}

	public function returnCcte($folder, $month, $year)
	{
		$url = sprintf($this->urlCcte, $folder, $month, $year);
		/** Test way **/
		//return json_decode($this->testCcte());
		/** Object Way **/
		$client = new Client();
		$response = $client->get($url);
		if($response){
			return json_decode($response->getBody()->getContents());
        }
        return [];
		/** RAW WAY**/
        $string = file_get_contents($url);
        $returnData = json_decode($string);
	}

	private function testCcte()
	{
		return '{"isvalid":true,"data":{"Clientes":{"0103003 - DE LEON SAGASTUME RICARDO":{"Cuentas":{"01- Honorarios":{"Movimientos":[],"SaldoInicial":{"SaldoPesos":0,"SaldoDolares":0},"SaldoFinal":{"SaldoPesos":0,"SaldoDolares":0}},"02 - Impuestos y Gastos":{"Movimientos":[],"SaldoInicial":{"SaldoPesos":0,"SaldoDolares":0},"SaldoFinal":{"SaldoPesos":0,"SaldoDolares":0}},"04 - Pendientes":{"Movimientos":[{"FECHA":"Sep 12 2018 12:00:00:000AM","Documento":"Pago de Terceros 000000051716 - Pago a Terceros : VARIOS por CLIENTE - Inefop  -P Rodr - Inefop  -P Rodr","SaldoPesos":10000,"SaldoDolares":0,"TipoCliente":"De Leon Ricardo","Cliente":"0103003 - DE LEON SAGASTUME RICARDO","UnidadNegocios":"Maldonado","TipoDoc":"04 - Pendientes","AcumuladoPesos":50000,"AcumuladoDolares":0},{"FECHA":"Sep 21 2018 12:00:00:000AM","Documento":"Pago de Terceros 000000051825 - Pago a Terceros : VARIOS por CLIENTE - PR Haro - Inefop - PR Haro - Inefop","SaldoPesos":10000,"SaldoDolares":0,"TipoCliente":"De Leon Ricardo","Cliente":"0103003 - DE LEON SAGASTUME RICARDO","UnidadNegocios":"Maldonado","TipoDoc":"04 - Pendientes","AcumuladoPesos":60000,"AcumuladoDolares":0}],"SaldoInicial":{"SaldoPesos":40000,"SaldoDolares":0},"SaldoFinal":{"SaldoPesos":60000,"SaldoDolares":0}}},"SubtotalCliente":{"SaldoPesos":60000,"SaldoDolares":0}}},"TotalGrupo":{"SaldoPesos":60000,"SaldoDolares":0},"Grupo":"Totales"}}';
	}

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
}
