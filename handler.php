<?php

class PaykeeperHandler
{
    public function index()
    {
        
        // получаем POST параметры
        $postData = $this->postData();
        
        // генерируем ссылку на оплату
        $paymentLink = $this->generatePaymentLink($postData);
        
        // меняем заказ в retailCRM, вставляем в кастомное поле заказа ссылку на оплату
        $editOrder = $this->editOrderInRetailCrm($postData, $paymentLink);
        
    }
    
    
    /**
     * 
     * Получаем POST параметры
     * 
     * @return array $paymentData - массив параметров, которые мы отправим на API Paykeeper для получения ссылки на оплату
    */
    
    public function postData() 
    {
        
        
        if (isset($_POST['orderId'])) $orderId                                   = $_POST['orderId']; else $orderId = '';
        if (isset($_POST['orderTotalSum'])) $orderTotalSum                       = $_POST['orderTotalSum']; else $orderTotalSum = '';
        if (isset($_POST['crmApiUrl'])) $crmApiUrl                               = $_POST['crmApiUrl']; else $crmApiUrl = '';
        if (isset($_POST['crmApiKey'])) $crmApiKey                               = $_POST['crmApiKey']; else $crmApiKey = '';
        if (isset($_POST['customerEmail'])) $customerEmail                       = $_POST['customerEmail']; else $customerEmail = 'test@test.ru';
        if (isset($_POST['customerName'])) $customerName                         = $_POST['customerName']; else $customerName = '';
        if (isset($_POST['siteCrm'])) $siteCrm                                   = $_POST['siteCrm']; else $siteCrm = '';
        
        $postData = [
            'orderId'                 =>  $orderId,
            'orderTotalSum'           =>  $orderTotalSum,
            'crmApiUrl'               =>  $crmApiUrl,
            'crmApiKey'               =>  $crmApiKey,
            'customerEmail'           =>  $customerEmail,
            'customerName'            =>  $customerName,
            'siteCrm'                 =>  $siteCrm,
        ];
        
        return $postData;
        
    }
    
    
    /**
     * 
     * Генерируем ссылку на оплату через API Paykeeper
     * 
     * @param array $postData - POST параметры
     * 
     * @return string $paymentLink - ссылка на оплату
    */    
    
    public function generatePaymentLink($postData)
    {
        
        if (    $postData['orderId'] != ''
            and $postData['orderTotalSum'] != ''
            and $postData['customerEmail'] != ''
            and $postData['customerName'] != '') {
                
                $dataRequest = [
                    "pay_amount"                => $postData['orderTotalSum'],
                    "clientid"                  => $postData['customerName'],
                    "orderid"                   => $postData['orderId'],
                    "client_email"              => $postData['customerEmail'],
                    "service_name"              => "Услуга",
                ];
                
                $paymentLink = $this->generatePaymentLinkRequest($dataRequest);
                
            } else {
                $paymentLink = 'Переданы не все POST параметры, ссылка на оплату не создалась.';
            }
        
        return $paymentLink;
    }
    
    
    /**
     * 
     * Метод отправки POST запроса через curl на API Paykeeper для генерации ссылки на оплату
     * 
     * @param array $curlRequest - POST параметры
     * 
     * @return array $response - ответ от paykeeper
    */ 
    
    public function generatePaymentLinkRequest($dataRequest)
    {
        
        // Логин и пароль от личного кабинета PayKeeper
        $user = "admin";
        $password = "UyHFBgqcfd0o";         
     
        // параметры заголовков для настройки CURLOPT_HTTPHEADER
        $base64 = base64_encode("$user:$password");         
        $curlHeaders = []; 
        array_push($curlHeaders,'Content-Type: application/x-www-form-urlencoded');
        array_push($curlHeaders,'Authorization: Basic '.$base64);
        
        // адрес сервера PayKeeper
        $server_paykeeper = "https://dmtrpedals.server.paykeeper.ru"; 
        // ссылка на полуение токена
        $uri_auth = "/info/settings/token/";
        // ссылка на генерацию ссылки на оплату
        $uri_generate = "/change/invoice/preview/";


        # перый этап - получаем токен
        $curlUrl = $server_paykeeper.$uri_auth;
        $tokenRequest = $this->curlGet($curlUrl, $curlHeaders);
        if (isset($tokenRequest['token'])) $token = $tokenRequest['token']; else $token = '';

        # второй этап - генерируем ссылку на оплату
        if ($token != '') {
            
            $curlUrl = $server_paykeeper.$uri_generate;
            $curlRequest = http_build_query(array_merge($dataRequest, array ('token' => $token)));
            $paymentLinkRequest = $this->curlPost($curlUrl, $curlHeaders, $curlRequest);
            if (isset($paymentLinkRequest['invoice_id'])) $invoice_id = $paymentLinkRequest['invoice_id']; else $paymentLink = '';
            $paymentLink = "http://$server_paykeeper/bill/$invoice_id/";
        } else {
            $paymentLink = '';
        }
        
        return $paymentLink;
        
    }
    
    /**
     * 
     * Отправка GET запроса посредством curl
     * 
     * @param string $curlUrl - url ссылка
     * @param array $headers - настройки CURLOPT_HTTPHEADER
     * 
     * @return array $response - результат работы curl
    */     
    
    public function curlGet($curlUrl, $curlHeaders)
    {
        
        
        $curl = curl_init(); 
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL, $curlUrl);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($curl,CURLOPT_HEADER,false);
        $response = curl_exec($curl); 
        $response = json_decode($response,true);   

        
        return $response;
        
    }
    
    
    /**
     * 
     * Отправка POST запроса посредством curl
     * 
     * @param string $curlUrl - url ссылка
     * @param array $headers - настройки CURLOPT_HTTPHEADER
     * @param array $request - параметры POST
     * 
     * @return array $response - результат работы curl
    */      
    
    public function curlPost($curlUrl, $curlHeaders, $curlRequest)
    {
        
        $curl = curl_init(); 
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL, $curlUrl);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($curl,CURLOPT_HEADER,false);
        curl_setopt($curl,CURLOPT_POSTFIELDS, $curlRequest);
        $response = curl_exec($curl); 
        $response = json_decode($response,true);     
        
        return $response;
        
    }    
    
    
    /**
     * 
     * Изменяем заказ в срм, при успешной генерации ссылки на оплату вставляем в кастомное поле в заказе
     * 
     * @param array $dataOrder - параметры POST
     * @param string $paymentLink - ссылка на оплату
     * 
     * @return void
    */      
    
    public function editOrderInRetailCrm($dataOrder, $paymentLink)
    {
        
        if ($paymentLink == '') {
            $paymentLink = 'Ошибка при генерации ссылки на оплату в сервисе PaymentKeeper';
        }        
        
        $crmDomain = $dataOrder['crmApiUrl'];
        $crmKey = $dataOrder['crmApiUrl'];      
        $siteCrm = $dataOrder['siteCrm'];      
        
        if ($crmDomain != '' and $crmKey != '' and $siteCrm != '') {
            
            $curlUrl = $crmDomain . '/api/v5/orders/'.$dataOrder['orderId'].'/edit';
        
            $postData = [
                'by' => 'id',
                'site' => $siteCrm,
                'order' => json_encode([
                    'customFields' => [
                        'url_payment_keeper' => $paymentLink   
                    ]
                ]),
                'apiKey' => 'e6XWaomAPezkM9QHC5frAXBK7kaKGCcR',    
            ];
            
            
            
            $curlHeaders = []; 
            array_push($curlHeaders,'Content-Type: application/x-www-form-urlencoded');            
            
            $postData = http_build_query($postData);
            
            $editOrder = $this->curlPost($curlUrl, $curlHeaders, $postData);

        }

    }
    

}
