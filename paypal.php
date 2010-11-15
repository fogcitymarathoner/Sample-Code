
    function testpayment1($step=1) {

        //$this->Ssl->force();
        $this->set('step',$step);
        // Get user inputs
        if ($step==1){
            //turn php errors on
            ini_set('track_errors', true);
           
            //retrieve ini info
            $paramslist = parse_ini_file("paypal_testpayment_1.ini", true);       
            //debug($paramslist);
            $this->set(compact('paramslist'));
        }
        // Get Transaction from PayPay
        elseif($step==2){


            //set APAPI URL
            //$url = trim('https://svcs.sandbox.paypal.com/AdaptivePayments/Pay');           
            //debug($this->data);
            //Create request content

            $body_data = http_build_query($this->data['BODY'], '', chr(38));
            // receiver info built seperatly since the parse_ini_file() function won't read the syntax
            $receiver_data = '';
            foreach ($this->data['RECEIVER'] as $key => $value)
            {
       
                $num = substr($key, -1);
                $key = substr($key,0, strlen($key)-1);
                $receiver_data = $receiver_data . chr(38) . trim(sprintf("receiverList.receiver(%u).%s ",$num, $key)) . '=' . $value ;
              
            }
       
            $body_data = $body_data . $receiver_data;
           
       
                /*TODO
                 * 
                 * The request and the headers contain sample test data
                 * Change the data with valid data applicable to your application
                 */
            try
            {
                //create request and add headers
                $params = array('http' => array(
                              'method' => "POST",
                              'content' => $body_data,
                              'header' =>  'Content-type: application/x-www-form-urlencoded'."\r\n".'X-PAYPAL-SECURITY-USERID: ' . $this->data['HEADERS']['X-PAYPAL-SECURITY-USERID'] . "\r\n" .
                              'X-PAYPAL-REQUEST-DATA-FORMAT: ' . $this->data['HEADERS']['X-PAYPAL-REQUEST-DATA-FORMAT']. "\r\n" .
                              'X-PAYPAL-RESPONSE-DATA-FORMAT: ' . $this->data['HEADERS']['X-PAYPAL-RESPONSE-DATA-FORMAT']. "\r\n" .
                              'X-PAYPAL-SECURITY-PASSWORD: ' . $this->data['HEADERS']['X-PAYPAL-SECURITY-PASSWORD'] . "\r\n" .
                              'X-PAYPAL-SECURITY-SIGNATURE: ' . $this->data['HEADERS']['X-PAYPAL-SECURITY-SIGNATURE']. "\r\n" .
                     'X-PAYPAL-APPLICATION-ID: ' . $this->data['HEADERS']['X-PAYPAL-APPLICATION-ID']. "\r\n" .
                              'CLIENT_AUTH: ' . $this->data['HEADERS']['CLIENT_AUTH']. "\r\n" .
                              'X-PAYPAL-SERVICE-VERSION: ' . $this->data['HEADERS']['X-PAYPAL-SERVICE-VERSION']. "\r\n"
                           ));
                            //debug($params);exit;
                //create stream context
                 $ctx = stream_context_create($params);
           
                //open the stream and send request
                 $fp = fopen($this->PaypalAdaptive->getPaypalPaymentURL(), 'r', false, $ctx);
               //get response
              $response = stream_get_contents($fp);
           
              //check to see if stream is open
                     if ($response === false) {
                    throw new Exception("php error message = " . "$php_errormsg");
                 }
                   //echo $response;
                   //close the stream
                   fclose($fp);
                   //parse the ap key from the response
                    $key = explode("&",$response);
           
//debug($key[4]);debug($payPalURL);exit;
                    //print the url to screen for testing purposes
                        $payPalURL = str_replace("payKey", "paykey", $this->Paypal->getPaypalApprovePaymentURL() . $key[4]);
                    If ( $key[1] == 'responseEnvelope.ack=Success')
                    {
                        //echo $payPalURL;
                        header('Location: '.$payPalURL);
                    }
                     else
                     {
                         echo 'ERROR: ' . $key[4];
                     }

            }
           
            catch(Exception $e)
              {
              echo 'Message: ||' .$e->getMessage().'||';
              }
    }
        //show the confirmation
        elseif($step==3){
            $result = $this->Paypal->processPayment($this->Session->read('result'),"DoExpressCheckoutPayment");
        //Detect errors
            $ack = strtoupper($result["ACK"]);
            if($ack!="SUCCESS"){
                $error = $result['L_LONGMESSAGE0'];
                $this->set('error',$error);
            }
            else {
                $this->set('result',$this->Session->read('result'));
            }
        }
       
    }