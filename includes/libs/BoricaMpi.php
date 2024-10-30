<?php

/**
 * BORICA AD MPI class
 */
class BoricaMpi
{

    private $language;

    private $mpiUrl;

    private $merchantId;

    private $merchantName;

    private $terminalId;

    private $privateKey;

    private $privateKeyPassword;

    private $publicKey;

    public function __construct($language, $mpiUrl, $merchantId, $merchantName, $terminalId, $privateKey, $privateKeyPassword, $publicKey)
    {
        $this->language = $language;
        $this->mpiUrl = $mpiUrl;
        $this->merchantId = $merchantId;
        $this->merchantName = $merchantName;
        $this->terminalId = $terminalId;
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->privateKeyPassword = $privateKeyPassword;
    }

    private function getRandomHex($num_bytes = 16)
    {
        return strtoupper(bin2hex(openssl_random_pseudo_bytes($num_bytes)));
    }

    private function signData($data) {
        // Подписване на съобщението с цифров сертификат
        $private_key_id = openssl_get_privatekey($this->privateKey, $this->privateKeyPassword);
        openssl_sign($data, $signature, $private_key_id, OPENSSL_ALGO_SHA256);

        // Формиране окончателна подписана сигнатура
        $P_SIGN = strtoupper(bin2hex($signature));
        return $P_SIGN;
    }

    public function validateSignature($signedData, $signature)
    {
        // Верифицеране на съобщението с цифров сертификат
        $public_key_id = openssl_get_publickey($this->publicKey);
        $ssl_verification = openssl_verify($signedData, $signature, $public_key_id, OPENSSL_ALGO_SHA256);

        $result = '';
        if ($ssl_verification == 1) {
            $result = 'VALID';
        } else if ($ssl_verification == 0) {
            $result = 'INVALID';
        } else {
            $result = openssl_error_string();
        }
        return $result;
    }

    public function generateOrderRequestFields($orderId, $description, $amount, $currency, $country, $customOrderId, $backReferenceUrl)
    {
        //date_default_timezone_set("UTC");

        $TRTYPE = "1"; // Тип на транзацията
        $TERMINAL = $this->terminalId; // Идентификатор на терминала получен от БОРИКА
        $MERCHANT = $this->merchantId; // Идентификатор на търговеца получен от БОРИКА
        $MERCH_NAME = $this->merchantName; // Име на търговеца
        $COUNTRY = strtoupper($country);
        $TIMESTAMP = gmdate("YmdHis"); // a) Формат: YYYYMMDDHHMMSS
        $LANG = strtoupper($this->language);
        if (!in_array($LANG, array('BG', 'EN'))) {
            $LANG = 'EN';
        }
        $BACKREF = $backReferenceUrl;
        //egeorgiev 20221206
        //$NONCE = $this->getRandomHex();
        $NONCE = bin2hex(str_pad($customOrderId, 12, "0", STR_PAD_LEFT)).$this->getRandomHex(4);
        $MERCH_TOKEN_ID = '-';

        $ORDER = str_pad($orderId, 6, "0", STR_PAD_LEFT); // Номер поръчка, 6 знака с водещи нули
        $DESC = substr($description, 0, 50); // Описание на плащането, Пример: "Тестова поръчка"
        $AMOUNT = number_format($amount, 2, '.', ''); // Сума на плащането, Формат: xx.xx, Пример: 12.34
        $CURRENCY = $currency; // Валута на плащането
        $AD_CUST_BOR_ORDER_ID = $ORDER . 'ORD@' . substr($customOrderId, 0, 16);

        $data =
            strlen($TERMINAL) . $TERMINAL .
            strlen($TRTYPE) . $TRTYPE .
            strlen($AMOUNT) . $AMOUNT .
            strlen($CURRENCY) . $CURRENCY .
            strlen($ORDER) . $ORDER .
//             strlen($MERCHANT) . $MERCHANT .
            strlen($TIMESTAMP) . $TIMESTAMP .
            strlen($NONCE) . $NONCE .
            $MERCH_TOKEN_ID;

        // Подписване
        $P_SIGN = $this->signData($data);

        $fields = array();
        $fields['AMOUNT'] = $AMOUNT;
        $fields['CURRENCY'] = $CURRENCY;
        $fields['DESC'] = $DESC;
        $fields['TERMINAL'] = $TERMINAL;
        $fields['MERCH_NAME'] = $MERCH_NAME;
        $fields['MERCHANT'] = $MERCHANT;
        $fields['TRTYPE'] = $TRTYPE;
        $fields['ORDER'] = $ORDER;
        $fields['TIMESTAMP'] = $TIMESTAMP;
        $fields['NONCE'] = $NONCE;
        $fields['LANG'] = $LANG;
        $fields['BACKREF'] = $BACKREF;
        $fields['ADDENDUM'] = 'AD,TD';
        $fields['AD.CUST_BOR_ORDER_ID'] = $AD_CUST_BOR_ORDER_ID;
        $fields['COUNTRY'] = $COUNTRY;
        $fields['P_SIGN'] = $P_SIGN;

        return $fields;
    }

    public function getSignedDataFromOrderResponseFields($data)
    {
        $ACTION = strlen($data['ACTION']) > 0 ? strlen($data['ACTION']) . $data['ACTION'] : "-";
        $RC = strlen($data['RC']) > 0 ? strlen($data['RC']) . $data['RC'] : "-";
        $APPROVAL = strlen($data['APPROVAL']) > 0 ? strlen($data['APPROVAL']) . $data['APPROVAL'] : "-";
        $TERMINAL = strlen($data['TERMINAL']) > 0 ? strlen($data['TERMINAL']) . $data['TERMINAL'] : "-";
        $TRTYPE = strlen($data['TRTYPE']) > 0 ? strlen($data['TRTYPE']) . $data['TRTYPE'] : "-";
        $AMOUNT = strlen($data['AMOUNT']) > 0 ? strlen($data['AMOUNT']) . $data['AMOUNT'] : "-";
        $CURRENCY = strlen($data['CURRENCY']) > 0 ? strlen($data['CURRENCY']) . $data['CURRENCY'] : "-";
        $ORDER = strlen($data['ORDER']) > 0 ? strlen($data['ORDER']) . $data['ORDER'] : "-";
        $RRN = strlen($data['RRN']) > 0 ? strlen($data['RRN']) . $data['RRN'] : "-";
        $INT_REF = strlen($data['INT_REF']) > 0 ? strlen($data['INT_REF']) . $data['INT_REF'] : "-";
        $PARES_STATUS = strlen($data['PARES_STATUS']) > 0 ? strlen($data['PARES_STATUS']) . $data['PARES_STATUS'] : "-";
        $ECI = strlen($data['ECI']) > 0 ? strlen($data['ECI']) . $data['ECI'] : "-";
        $TIMESTAMP = strlen($data['TIMESTAMP']) > 0 ? strlen($data['TIMESTAMP']) . $data['TIMESTAMP'] : "-";
        $NONCE = strlen($data['NONCE']) > 0 ? strlen($data['NONCE']) . $data['NONCE'] : "-";
        $MERCH_TOKEN_ID = "-";

        $signedData = $ACTION . $RC . $APPROVAL . $TERMINAL . $TRTYPE . $AMOUNT . $CURRENCY . $ORDER . $RRN . $INT_REF . $PARES_STATUS . $ECI . $TIMESTAMP . $NONCE . $MERCH_TOKEN_ID;
        return $signedData;
    }

    public function getSignatureFromOrderResponseFields($data)
    {
        $signature = hex2bin($data['P_SIGN']);
        return $signature;
    }

    public function generateStatusCheckFields($orderId){
		$TRTYPE = "90"; // Тип на транзацията
        $TERMINAL = $this->terminalId; // Идентификатор на терминала получен от БОРИКА
        $ORDER = str_pad($orderId, 6, "0", STR_PAD_LEFT); // Номер поръчка, 6 знака с водещи нули
        $TRAN_TRTYPE = "1";
        $NONCE = $this->getRandomHex();
        $dataSign =
            strlen($TERMINAL) . $TERMINAL .
            strlen($TRTYPE) . $TRTYPE .
            strlen($ORDER) . $ORDER .
            strlen($NONCE) . $NONCE;
        $P_SIGN = $this->signData($dataSign);

        $fields = array();
        $fields['TERMINAL'] = $TERMINAL;
        $fields['TRAN_TRTYPE'] = $TRAN_TRTYPE;
        $fields['TRTYPE'] = $TRTYPE;
        $fields['ORDER'] = $ORDER;
        $fields['NONCE'] = $NONCE;
        $fields['P_SIGN'] = $P_SIGN;

        return $fields;
    }

    public function generateReversalFields($orderId,  $inteRef, $rrn, $amount, $reason, $currency){
        $TRTYPE = "24"; // Тип на транзацията
        $TERMINAL = $this->terminalId; // Идентификатор на терминала получен от БОРИКА
        $ORDER = str_pad($orderId, 6, "0", STR_PAD_LEFT); // Номер поръчка, 6 знака с водещи нули
        $AMOUNT = $amount; //Сума за връщане      
        $CURRENCY = $currency; //Валута на плащането
        $INT_REF = $inteRef;
        $RRN = $rrn;
        $TIMESTAMP = date("YmdHis");
        $NONCE = $this->getRandomHex();
        $MERCH_TOKEN_ID = '-';

        $dataSign =
            strlen($TERMINAL) . $TERMINAL .
            strlen($TRTYPE) . $TRTYPE .
            strlen($AMOUNT) . $AMOUNT .
            strlen($CURRENCY) . $CURRENCY .
            strlen($ORDER) . $ORDER .
            strlen($TIMESTAMP) . $TIMESTAMP .
            strlen($NONCE) . $NONCE .
            $MERCH_TOKEN_ID;
        $P_SIGN = $this->signData($dataSign);

        $fields = array();
        $fields['TERMINAL'] = $TERMINAL;
        $fields['TRTYPE'] = $TRTYPE;
        $fields['AMOUNT'] = $AMOUNT;
        $fields['CURRENCY'] = $CURRENCY;
        $fields['ORDER'] = $ORDER;
        $fields['RRN'] = $RRN;
        $fields['INT_REF'] = $INT_REF;
        $fields['TIMESTAMP'] = $TIMESTAMP;
        $fields['NONCE'] = $NONCE;
        $fields['P_SIGN'] = $P_SIGN;

        return $fields;
	}
    /**
    * Post request
    *
    * @param array $postdata
    */
    public function makePOSTrequest($postdata)
    {       
        $args = array(
            'timeout'     => 30,           
            'body' => $postdata
          );        
        $request = wp_remote_post( $this->mpiUrl, $args );      
        if($request['response']['code'] == 200){       
            $response = ($request['body']); 
            $resultStdClassObject=json_decode($response, true);
            $response = $this->objectToArray($resultStdClassObject);
        }else{
            $response = 'Response code:' . $request['response']['code']. ' Response message: ' .$request['response']['message'];
        }
      return $response;
    }
    public function objectToArray($d) {
        if (is_object($d)) {
            $d = get_object_vars($d);
        }
        if (is_array($d)) {
            return array_map(array($this, 'objectToArray'), $d);
        }
        else {
             return $d;
        }
    }
}