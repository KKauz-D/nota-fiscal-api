<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GINFES SOAP Endpoints
    |--------------------------------------------------------------------------
    */

    'homolog_url' => env('GINFES_HOMOLOG_URL', 'https://isshomo.sefin.fortaleza.ce.gov.br/grpfor-iss/ServiceGinfesImplService?wsdl'),

    'prod_url' => env('GINFES_PROD_URL', 'https://iss.fortaleza.ce.gov.br/grpfor-iss/ServiceGinfesImplService?wsdl'),

    /*
    |--------------------------------------------------------------------------
    | Municipality Settings
    |--------------------------------------------------------------------------
    */

    'cod_municipio' => env('GINFES_COD_MUNICIPIO', '2304400'),

    /*
    |--------------------------------------------------------------------------
    | GINFES XML Versioning
    |--------------------------------------------------------------------------
    */

    'cabecalho_versao' => '3',

    'xsd_path' => storage_path('xsd'),

    /*
    |--------------------------------------------------------------------------
    | XML Namespaces
    |--------------------------------------------------------------------------
    */

    'namespaces' => [
        'enviar_lote' => 'http://www.ginfes.com.br/servico_enviar_lote_rps_envio_v03.xsd',
        'tipos' => 'http://www.ginfes.com.br/tipos_v03.xsd',
        'consultar_situacao' => 'http://www.ginfes.com.br/servico_consultar_situacao_lote_rps_envio_v03.xsd',
        'consultar_lote' => 'http://www.ginfes.com.br/servico_consultar_lote_rps_envio_v03.xsd',
        'cancelar' => 'http://www.ginfes.com.br/servico_cancelar_nfse_envio_v02.xsd',
    ],

];
