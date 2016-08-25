<?php

function pagseguroapi_config()
{
    return array(
        'FriendlyName' => array(
            "Type" => "System",
            "Value" => "PagSeguro API"
        ),
        'sandbox' => array(
            "FriendlyName" => "Ativar Sandbox",
            "Type" => "yesno",
            "Description" => "Voc&ecirc; pode usar o PagSeguro Sandbox para testar os pagamentos.<br><b>Nota:</b> voc&ecirc; deve usar um token de desenvolvedor que pode ser encontrado em <a target='_blank' href='https://sandbox.pagseguro.uol.com.br/vendedor/configuracoes.html'>PagSeguro Sandbox</a>."
        ),
        'email' => array(
            "FriendlyName" => "Email cadastrado no PagSeguro",
            "Type" => "text",
            "Size" => "40"
        ),
        'token' => array(
            "FriendlyName" => "Token do PagSeguro",
            "Type" => "text",
            "Size" => "50",
            "Description" => "&Eacute; necess&aacute;rio para processar o pagamento e as notifica&ccedil;&otilde;es. &Eacute; poss&iacute;vel gerar um novo token <a target='_blank' href='https://pagseguro.uol.com.br/integracao/token-de-seguranca.jhtml'>aqui</a>."
        ),
        'mode' => array(
            "FriendlyName" => "Modo de Abertura",
            "Type" => "dropdown",
            "Options" => "Nova janela,Mesma janela,Lightbox",
            "Size" => "30",
            "Description" => "Defina o modo para abrir o processo de pagamento conforme o tipo de janela que prefira para o seu site."
        ),
	"auto_window" => array(
            "FriendlyName" => "Abrir Janela de Pagamento",
            "Type" => "yesno",
            "Description" => "Abrir janela de pagamento automaticamente ao acessar a fatura."
        ),
	"btn_pg" => array(
            "FriendlyName" => "Texto do Bot&atilde;o de Pagamento",
            "Type" => "text",
            "Size" => "30",
            "Default" => "Pagar agora"
        ),
	"taxa_percentual" => array(
            "FriendlyName" => "Taxa Percentual (%)",
            "Type" => "text",
            "Size" => "10",
            "Description" => "Taxa para adicionar &agrave; fatura. Ex: 5 (igual a 5%). O total ser&aacute; somando com a taxa auxiliar, se houver."
        ),
	"taxa_auxiliar" => array(
            "FriendlyName" => "Taxa Auxiliar",
            "Type" => "text",
            "Size" => "10",
            "Description" => "Valor fixo adicional para a fatura. Ex: 0.50 ou 1.00"
        ),
	"css" => array(
            "FriendlyName" => "-- Op&ccedil;&otilde;es de CSS",
            "Description" => "(n&atilde;o altere se n&atilde;o tiver certeza.) --"
        ),
	"btn_css" => array(
            "FriendlyName" => "Classe CSS do Bot&atilde;o de Pagamento",
            "Type" => "text",
            "Size" => "30",
            "Default" => "btn"
        ),
	"custom_css" => array(
            "FriendlyName" => "CSS Personalizado",
            "Type" => "textarea",
            "Rows" => "5",
            "Default" => ".btn { margin-top: 4px; }"
        ),
    );
}

function pagseguroapi_link($params)
{

    $taxa_percentual = ( $params['amount'] / 100) * $params['taxa_percentual'];
    $taxa_total = $taxa_percentual + $params['taxa_auxiliar'];
    $taxa_total = number_format($taxa_total, 2, '.', '');

    $xml_checkout = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<checkout>
    <currency>BRL</currency>
    <items>
        <item>
            <id>1</id>
            <description>'.htmlspecialchars($params['description']).'</description>
            <amount>'.$params['amount'].'</amount>
            <quantity>1</quantity>
        </item>
    </items>
    <extraAmount>'.$taxa_total.'</extraAmount>
    <sender>  
        <email>'.$params['clientdetails']['email'].'</email>
    </sender>
    <reference>'.$params['invoiceid'].'</reference>
    <redirectURL>'.$params['systemurl'].'/viewinvoice.php?id='.$params['invoiceid'].'</redirectURL>
    <notificationURL>'.$params['systemurl'].'/modules/gateways/'.basename(__FILE__).'</notificationURL>
</checkout>';

    if ( $params["sandbox"] ){
        $url_checkout = "https://ws.sandbox.pagseguro.uol.com.br/v2/checkout/";
        $url_payment = "https://sandbox.pagseguro.uol.com.br/v2/checkout/payment.html";
        $url_lightbox = "https://stc.sandbox.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.lightbox.js";
    } else {
        $url_checkout = "https://ws.pagseguro.uol.com.br/v2/checkout/";
        $url_payment = "https://pagseguro.uol.com.br/v2/checkout/payment.html";
        $url_lightbox = "https://stc.pagseguro.uol.com.br/pagseguro/api/v2/checkout/pagseguro.lightbox.js";
    }

    $curl = curl_init($url_checkout . '?email=' . $params['email'] . '&token=' . $params['token']);
    curl_setopt_array($curl, array(
        CURLOPT_POST           => 1,
        CURLOPT_POSTFIELDS     => $xml_checkout,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/xml; charset=UTF-8')));
    $retorno_curl    = curl_exec($curl);
    $checkout_parsed = simplexml_load_string($retorno_curl);

    if( $params["mode"] == "Nova janela" ) $mode = "target='_blank'";
    if( $params["mode"] == "Mesma janela" ) $mode = "target='_self'";
    if( $params["mode"] == "Lightbox" ) $mode = "onsubmit='PagSeguroLightbox(this); return false;'";

    if ($checkout_parsed->code) {
        $result = '<form action="'.$url_payment.'" id="pagseguro" method="get" '.$mode.' >';
        $result .= '<input type="hidden" name="code" value="' . $checkout_parsed->code . '">';
        $result .= '<input type="submit" class="'.$params["btn_css"].'" value="'.$params["btn_pg"].'">';
        $result .= '</form>';
        $result .= '<style>'.$params["custom_css"].'</style>';
        if( $params["mode"] == "Lightbox" ) {
            $result .= '<script type="text/javascript" src="'.$url_lightbox.'"></script>';
        }
        if( $params["mode"] == "Lightbox" && $params["auto_window"] && !$_GET["success"] ) {
            $result .= '
                <script type="text/javascript">
                    var isOpenLightbox = PagSeguroLightbox({
                        code: "' . $checkout_parsed->code . '"
			}, {
			    success: function () {
			        window.location.href = "' . $params['systemurl'].'/viewinvoice.php?id='.$params['invoiceid'] . '&success=true";
			    },
			    abort: function () {
			        
			    }
			});
                    if ( ! isOpenLightbox ) {
                        window.location.href="'.$url_payment.'?code="+code;
                    }
                </script>
            ';
        }
        if ( $params["mode"] != "Lightbox" && $params["auto_window"] && !$_GET["success"] ) {
            $result .= '<script type="text/javascript">document.getElementById("pagseguro").submit()</script>';
        }
        if ( $params['taxa_percentual'] || $params['taxa_auxiliar'] ) {
            $result .= "<p>Taxa adicional: " . formatCurrency($taxa_total) . "</p>";
            $result .= "<p>Valor total &agrave; pagar: " . formatCurrency($params['amount'] + $taxa_total) . "</p>";
        }
    } else {
        $result = '<font style="color:red">Ocorreu um erro na comunica&ccedil;&atilde;o com o PagSeguro</font>';
        logTransaction($params['name'], $retorno_curl . print_r($params, true) . ($checkout_parsed ? " / " . $checkout_parsed : ""), 'Unsuccessful');
    }
    return $result;
}

if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    if (!array_key_exists('notificationCode', $_POST) || !array_key_exists('notificationType', $_POST)) {
        header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
        die();
    }
    require "../../init.php";
    $whmcs->load_function("gateway");
    $whmcs->load_function("invoice");

    $GATEWAY = getGatewayVariables('pagseguroapi');

    if ( $GATEWAY['sandbox'] ){
        $url_notifications = "https://ws.sandbox.pagseguro.uol.com.br/v3/transactions/notifications/";
    } else {
        $url_notifications = "https://ws.pagseguro.uol.com.br/v3/transactions/notifications/";		
    }

    $curl = curl_init($url_notifications . '' . $_POST['notificationCode'] . '?email=' . $GATEWAY['email'] . '&token=' . $GATEWAY['token']);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $xml = simplexml_load_string(curl_exec($curl));

    logTransaction($GATEWAY['name'], print_r($_POST, true) . print_r($xml, true), 'Successful');
    $invoiceid = checkCbInvoiceID($xml->reference, $GATEWAY["name"]);
    checkCbTransID($xml->code);

    if ($xml->status == 3 || $xml->status == 4) {
        $valor_pagamento = (float)$xml->grossAmount - (float)$xml->extraAmount;
        $taxas = (float)$xml->grossAmount - (float)$xml->netAmount;
        addInvoicePayment($invoiceid, $xml->code, $valor_pagamento, $taxas, 'pagseguroapi');
    }
}

?>