<?php

/**
 * Silver Touch Technologies Limited.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 *
 * @category   Sttl
 * @package    Sttl_Authorizecim
 * @copyright  Copyright (c) 2011 Silver Touch Technologies Limited. (http://www.silvertouch.com/MagentoExtensions)
 * @license    http://www.silvertouch.com/MagentoExtensions/LICENSE.txt
 */
class Sttl_Authorizecim_Model_Api extends Mage_Payment_Model_Method_Abstract {

    /** CIM gateway url live */
    const CGI_URL = 'https://api.authorize.net/xml/v1/request.api';

    /** CIM gateway url test */
    const CGI_URL_TEST = 'https://apitest.authorize.net/xml/v1/request.api';
    const XML_PATH_GENERATION_LOGIN = 'payment/authorizecim/login';
    const XML_PATH_GENERATION_TRANSACTION = 'payment/authorizecim/trans_key';

    protected $_code = 'authorizecim';

    /**
     * Fields that should be replaced in debug with '***'
     *
     * @var array
     */
    protected $_debugReplacePrivateDataKeys = array('name', 'transactionKey', 'cardNumber', 'expirationDate', '');

    /**
     * Authorize
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_GoogleCheckout_Model_Payment
     */
    public function getPaymentCreditCard($profileId) {
        $customerProfile = $this->getCustomerProfile($profileId);
        return $customerProfile;
    }

    public function getPaymentDetail($transactionId) {
        $customertransactionDetail = $this->getCustomerTransactionDetail($transactionId);
        return $customertransactionDetail;
    }

    /**
     * Authorize.NET
     *
     * @return  merchant Authentiocation xml request
     */
    protected function merchantAuthenticationBlock() {
        return "<merchantAuthentication>" .
                "<name>" . Mage::getStoreConfig(self::XML_PATH_GENERATION_LOGIN) . "</name>" .
                "<transactionKey>" . Mage::getStoreConfig(self::XML_PATH_GENERATION_TRANSACTION) . "</transactionKey>" .
                "</merchantAuthentication>";
    }

    /**
     * Authorize.NET
     *
     * @param   Varien_Object $orderPayment
     * @param   customerProfileId of Authorize.net
     * @return  Payment Profile Id from Authorize.net
     */
    protected function checkPaymentProfile($customerProfileId, Varien_Object $payment) {
        $ccId = substr($payment->getCcNumber(), -4);
        $cardExists = array();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<getCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "</getCustomerProfileRequest>";

        $customerresponse = $this->sendRequestViaCurl($content);
        $parsedresponse = simplexml_load_string($customerresponse, "SimpleXMLElement", LIBXML_NOWARNING);
        if ($parsedresponse) {
            if (Sttl_Authorizecim_Model_Payment::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
                $message = Mage::helper('paygate')->__("The operation failed with the following errors: ");
                foreach ($parsedresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($parsedresponse->messages->message)){
                    $message = strip_tags($customerresponse);
                }
                Mage::throwException($message);
            } else {
                $paymentProfiles = $parsedresponse->profile->paymentProfiles;
                if (count($paymentProfiles)) {
                    foreach ($paymentProfiles as $profile) {
                        $paymentProfileId = $profile->customerPaymentProfileId;
                        $cardNumber = substr($profile->payment->creditCard->cardNumber, -4);

                        if ($ccId != $cardNumber) {
                            $cardExists[] = 'false';
                        } else {
                            $cardExists[] = 'true';
                            break;
                        }
                    }
                }
                if (in_array('true', $cardExists)) {
                    return $paymentProfileId;
                } else {
                    return '';
                }
                $cimCollection = Mage::getModel('authorizecim/transaction')->getCollection()
                        ->addFieldToFilter('payment_id', $paymentProfileId);

                if ($cimCollection->count()) {
                    $cimCollection = $cimCollection->getFirstItem();
                    return $customerPaymentProfileId = $cimCollection->getPaymentId();
                } else {
                    return '';
                }
            }
        } else {
            if($customerresponse){
                $message = strip_tags($customerresponse);
            }else{
                $message = Mage::helper('paygate')->__("There has been some error occured while getting payment response.");
            }
            Mage::throwException($message);
        }

        //return $content;
    }

    /**
     * Send request to gateway for fetching customer profile details
     *
     * @param   customerProfileId of Authorize.net
     * @return  Payment Profile  from Authorize.net
     */
    protected function getCustomerProfile($customerProfileId) {
        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<getCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "</getCustomerProfileRequest>";

        $customerresponse = $this->sendRequestViaCurl($content);
        if ($customerresponse) {
            $parsedresponse = simplexml_load_string($customerresponse, "SimpleXMLElement", LIBXML_NOWARNING);
            if (Sttl_Authorizecim_Model_Payment::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
                return false;
            } else {
                $paymentProfiles = $parsedresponse->profile->paymentProfiles;
                if (count($paymentProfiles)) {
                    return $paymentProfiles;
                }
            }
        } else {
            return false;
        }
    }

    protected function getCustomerTransactionDetail($customertransactionId) {
        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<getTransactionDetailsRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<transId>" . $customertransactionId . "</transId>" .
                "</getTransactionDetailsRequest>";
        $customerresponse = $this->sendRequestViaCurl($content);
        $parsedresponse = simplexml_load_string($customerresponse, "SimpleXMLElement", LIBXML_NOWARNING);
        if (Sttl_Authorizecim_Model_Payment::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
            return false;
        } else {
            $parsedresponse = $parsedresponse->transaction->payment->creditCard;
            if (count($parsedresponse)) {
                return $parsedresponse;
            }
        }
    }

    /**
     * Check Shippping profile at Authorize.net
     *
     * @param   customerProfileId of Authorize.net
     * @return  Shipping Id from Authorize.net
     */
    protected function checkShippingProfile($customerProfileId) {
        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<getCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "</getCustomerProfileRequest>";
        $customerresponse = $this->sendRequestViaCurl($content);
        $parsedresponse = simplexml_load_string($customerresponse, "SimpleXMLElement", LIBXML_NOWARNING);
        if (Sttl_Authorizecim_Model_Payment::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
            $message = Mage::helper('paygate')->__('The operation failed with the following errors: ');
            foreach ($parsedresponse->messages->message as $msg) {
                $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
            }
            if(!count($parsedresponse->messages->message)){
                $message = strip_tags($customerresponse);
            }
            Mage::throwException($message);
        } else {
            $shippingProfileId = $parsedresponse->profile->shipToList->customerAddressId;
            $cimCollection = Mage::getModel('authorizecim/transaction')->getCollection()
                    ->addFieldToFilter('shipping_id', $shippingProfileId);

            if ($cimCollection->count()) {
                $cimCollection = $cimCollection->getFirstItem();
                return $customerPaymentProfileId = $cimCollection->getShippingId();
            } else {
                return '';
            }
        }
    }

    /**
     * Create Customer Profile Authorize.net Request
     *
     * @param   customerProfileId of Authorize.net
     */
    protected function _buildCustomerRequest(Varien_Object $payment) {
        $order = $payment->getOrder();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<profile>";

        if (!empty($order)) {
            $billing = $order->getBillingAddress();
            $cust_email = $billing->getEmail();
            if (!empty($cust_email)) {
                $customer_email = $cust_email;
            } else {
                $quote = Mage::getSingleton('checkout/session')->getQuote();
                $billingAddress = $quote->getBillingAddress();
                $customer_email = $billingAddress->getEmail();
            }

            $content .="<description></description>" .
                    "<email>" . $customer_email . "</email>";
        }

        $content .="</profile>" .
                "</createCustomerProfileRequest>";

        return $content;
    }

    /**
     * Delete Customer Profile Authorize.net Request
     *
     * @param   customerProfileId of Authorize.net
     */
    protected function _deleteCustomerProfile($customerProfileId, $isNew) {
        if ($isNew) {
            $request = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                    "<deleteCustomerProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                    $this->merchantAuthenticationBlock() .
                    "<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                    "</deleteCustomerProfileRequest>";
            $this->sendRequestViaCurl($request);
        }
    }

    /**
     * Send request via Fsockopen
     */
    protected function sendRequestViaFsockopen($host, $path, $content) {
        $posturl = "ssl://" . $host;
        $header = "Host: $host\r\n";
        $header .= "User-Agent: PHP Script\r\n";
        $header .= "Content-Type: text/xml\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n";
        $header .= "Connection: close\r\n\r\n";
        $fp = fsockopen($posturl, 443, $errno, $errstr, 30);
        if (!$fp) {
            $body = false;
        } else {
            error_reporting(E_ERROR);
            fputs($fp, "POST $path   HTTP/1.1\r\n");
            fputs($fp, $header . $content);
            fwrite($fp, $out);
            $response = "";
            while (!feof($fp)) {
                $response = $response . fgets($fp, 128);
            }
            fclose($fp);
            error_reporting(E_ALL ^ E_NOTICE);

            $len = strlen($response);
            $bodypos = strpos($response, "\r\n\r\n");
            if ($bodypos <= 0) {
                $bodypos = strpos($response, "\n\n");
            }
            while ($bodypos < $len && $response [$bodypos] != '<') {
                $bodypos++;
            }
            $body = substr($response, $bodypos);
        }
        return $body;
    }

    /**
     * Send request via Curl
     */
    protected function sendRequestViaCurl($content) {
        $this->_debug(array('request' => $content));
        $err = false;
        $posturl = $this->getConfigData('cgi_url');
        $posturl = $posturl ? $posturl : self::CGI_URL;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $posturl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        //curl_setopt($ch, CURLOPT_PROXY, "192.168.0.6:3128");
        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
        }
        curl_close($ch);
        if ($err) {
            Mage::log(Mage::helper('paygate')->__($err));
            return;
        }
        $len = strlen($response);
        $bodypos = strpos($response, "\r\n\r\n");
        if ($bodypos <= 0) {
            $bodypos = strpos($response, "\n\n");
        }
        while ($bodypos < $len && $response[$bodypos] != '<') {
            $bodypos++;
        }
        $body = substr($response, $bodypos);
        $this->_debug(array('response' => $body));
        return $body;
    }

    /**
     * Prepare request to gateway for creating customer payment profile
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Varien_Object $orderPayment
     * @return unknown
     */
    protected function _buildPaymentRequest(Varien_Object $payment, $customerProfileId) {
        $order = $payment->getOrder();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerPaymentProfileRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<paymentProfile>";

        if (!empty($order)) {
            $billing = $order->getBillingAddress();
            $cust_email = $billing->getEmail();
            if (!empty($cust_email)) {
                $customer_email = $cust_email;
                $first_name = $billing->getFirstname();
                $last_name = $billing->getLastname();
                $telephone = $billing->getTelephone();
            } else {
                $quote = Mage::getSingleton('checkout/session')->getQuote();
                $billingAddress = $quote->getBillingAddress();
                $customer_email = $billingAddress->getEmail();
                $first_name = $billingAddress->getFirstname();
                $last_name = $billingAddress->getLastname();
                $telephone = $billingAddress->getTelephone();
            }
            $content .="<billTo>" .
                    "<firstName>" . $first_name . "</firstName>" .
                    "<lastName>" . $last_name . "</lastName>" .
                    "<phoneNumber>" . $telephone . "</phoneNumber>" .
                    "</billTo>";
        }
        $content .="<payment>" .
                "<creditCard>" .
                "<cardNumber>" . $payment->getCcNumber() . "</cardNumber>" .
                "<expirationDate>" . sprintf('%04d-%02d', $payment->getCcExpYear(), $payment->getCcExpMonth()) . "</expirationDate>" . // required format for API is YYYY-MM
                "</creditCard>" .
                "</payment>" .
                "</paymentProfile>" .
                //"<validationMode>testMode</validationMode>". // liveMode or testMode
                "</createCustomerPaymentProfileRequest>";
        return $content;
    }

    /**
     * Prepare request to gateway for creating customer shipping address request
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Varien_Object $orderPayment
     * @return unknown
     */
    protected function _buildShippingRequest(Varien_Object $payment, $customerProfileId) {
        $order = $payment->getOrder();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerShippingAddressRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<address>";
        if (!empty($order)) {
            $shipping = $order->getShippingAddress();
            $billing = $order->getBillingAddress();
            /*  added fix for virtual products  */
            if(!$shipping){
                $shipping = $billing;
            }
            /*  added fix for virtual products  */
            $content .="<firstName>" . $shipping->getFirstname() . "</firstName>" .
                    "<lastName>" . $shipping->getLastname() . "</lastName>" .
                    "<company>" . $shipping->getCompany() . "</company>" .
                    "<address>" . $shipping->getStreet(1) . "</address>" .
                    "<city>" . $shipping->getCity() . "</city>" .
                    "<state>" . $shipping->getRegion() . "</state>" .
                    "<zip>" . $shipping->getPostcode() . "</zip>" .
                    "<country>" . $shipping->getCountry() . "</country>" .
                    "<phoneNumber>" . $billing->getTelephone() . "</phoneNumber>" .
                    "<faxNumber>" . $billing->getFax() . "</faxNumber>";
        }

        $content .="</address>" .
                "</createCustomerShippingAddressRequest>";
        return $content;
    }

    /**
     * Prepare request to gateway for creating customer profile transaction
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Varien_Object $orderPayment
     * @return unknown
     */
    protected function _buildTransactionRequest(Varien_Object $payment, $customerProfileId, $paymentProfileId, $ShippingAddressId) {
        $order = $payment->getOrder();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<transaction>" .
                "<profileTransAuthOnly>" .
                "<amount>" . $payment->getAmount() . "</amount>" // should include tax, shipping, and everything.
        ;

        if ($order->getTaxAmount()) {
            $content .="<tax>" .
                    "<amount>" . $order->getTaxAmount() . "</amount>" .
                    "<name>Tax</name>" .
                    "<description>Tax</description>" .
                    "</tax>";
        }

        $shipping_method = $order->getShippingMethod();
        if (empty($shipping_method)) {
            $shipping_method = $order->getShippingDescription();
        }

        $content.="<shipping>" .
                "<amount>" . $order->getShippingAmount() . "</amount>" .
                "<name>" . $shipping_method . "</name>" .
                "<description>" . $order->getShippingDescription() . "</description>" .
                "</shipping>";
        if (!empty($order)) {

            $items = $order->getAllItems();

            foreach ($items as $_item) {

                $content .="<lineItems>" .
                        "<itemId>" . $_item->getId() . "</itemId>" .
                        "<name><![CDATA[" . substr($_item->getName(), 0, 30) . "]]></name>" .
                        "<description>Description of item sold</description>" .
                        "<quantity>" . $_item->getQtyOrdered() . "</quantity>" .
                        "<unitPrice>" . $_item->getPrice() . "</unitPrice>" .
                        "<taxable>false</taxable>" .
                        "</lineItems>";
            }
        }
        $content .="<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<customerPaymentProfileId>" . $paymentProfileId . "</customerPaymentProfileId>" .
                "<customerShippingAddressId>" . $ShippingAddressId . "</customerShippingAddressId>";

        if ($order && $order->getIncrementId()) {
            $content .="<order>" .
                    "<invoiceNumber>" . $order->getIncrementId() . "</invoiceNumber>" .
                    "</order>";
        }

        $content .="</profileTransAuthOnly>" .
                "</transaction>" .
                "</createCustomerProfileTransactionRequest>";
        return $content;
    }

    /**
     * Prepare request to gateway for creating customer profile transaction
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Varien_Object $orderPayment
     * @return unknown
     */
    protected function _buildTransactionCaptureRequest(Varien_Object $payment, $customerProfileId, $paymentProfileId, $ShippingAddressId) {
        $order = $payment->getOrder();
        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<transaction>" .
                "<profileTransAuthCapture>" .
                "<amount>" . $payment->getAmount() . "</amount>"; // should include tax, shipping, and everything.


        if ($order->getTaxAmount()) {
            $content .="<tax>" .
                    "<amount>" . $order->getTaxAmount() . "</amount>" .
                    "<name>Tax</name>" .
                    "<description>Tax</description>" .
                    "</tax>";
        }

        $shipping_method = $order->getShippingMethod();
        if (empty($shipping_method)) {
            $shipping_method = $order->getShippingDescription();
        }

        $content.="<shipping>" .
                "<amount>" . $order->getShippingAmount() . "</amount>" .
                "<name>" . $shipping_method . "</name>" .
                "<description>" . $order->getShippingDescription() . "</description>" .
                "</shipping>";

        if (!empty($order)) {

            $items = $order->getAllItems();

            foreach ($items as $_item) {

                $content .="<lineItems>" .
                        "<itemId>" . $_item->getId() . "</itemId>" .
                        "<name><![CDATA[" . substr($_item->getName(), 0, 30) . "]]></name>" .
                        "<description>Description of item sold</description>" .
                        "<quantity>" . $_item->getQtyOrdered() . "</quantity>" .
                        "<unitPrice>" . $_item->getPrice() . "</unitPrice>" .
                        "<taxable>false</taxable>" .
                        "</lineItems>";
            }
        }
        $content .="<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<customerPaymentProfileId>" . $paymentProfileId . "</customerPaymentProfileId>" .
                "<customerShippingAddressId>" . $ShippingAddressId . "</customerShippingAddressId>";

        if ($order && $order->getIncrementId()) {
            $content .="<order>" .
                    "<invoiceNumber>" . $order->getIncrementId() . "</invoiceNumber>" .
                    "</order>";
        }

        $content .="</profileTransAuthCapture>" .
                "</transaction>" .
                "</createCustomerProfileTransactionRequest>";
        return $content;
    }

    /**
     * Prepare request to gateway for creating customer profile transaction
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Varien_Object $orderPayment
     * @return unknown
     */
    protected function _buildProAuthCaptureRequest(Varien_Object $payment, $customerProfileId, $paymentProfileId, $ShippingAddressId) {
        $order = $payment->getOrder();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<transaction>" .
                "<profileTransPriorAuthCapture>" .
                "<amount>" . $payment->getAmount() . "</amount>"; // should include tax, shipping, and everything.
        if ($order->getTaxAmount()) {
            $content .="<tax>" .
                    "<amount>" . $order->getTaxAmount() . "</amount>" .
                    "<name>Tax</name>" .
                    "<description>Tax</description>" .
                    "</tax>";
        }

        $shipping_method = $order->getShippingMethod();
        if (empty($shipping_method)) {
            $shipping_method = $order->getShippingDescription();
        }

        $content.=
                "<shipping>" .
                "<amount>" . $order->getShippingAmount() . "</amount>" .
                "<name>" . $shipping_method . "</name>" .
                "<description>" . $order->getShippingDescription() . "</description>" .
                "</shipping>";

        if (!empty($order)) {

            $items = $order->getAllItems();

            foreach ($items as $_item) {

                $content .="<lineItems>" .
                        "<itemId>" . $_item->getId() . "</itemId>" .
                        "<name><![CDATA[" . substr($_item->getName(), 0, 30) . "]]></name>" .
                        "<description>Description of item sold</description>" .
                        "<quantity>" . $_item->getQtyOrdered() . "</quantity>" .
                        "<unitPrice>" . $_item->getPrice() . "</unitPrice>" .
                        "<taxable>true</taxable>" .
                        "</lineItems>";
            }
        }
        $content .="<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<customerPaymentProfileId>" . $paymentProfileId . "</customerPaymentProfileId>" .
                "<customerShippingAddressId>" . $ShippingAddressId . "</customerShippingAddressId>" .
                "<transId>" . $payment->getCimTransactionid() . "</transId>";

        $content .="</profileTransPriorAuthCapture>" .
                "</transaction>" .
                "</createCustomerProfileTransactionRequest>";



        return $content;
    }

    /**
     * Prepare request to gateway for creating customer profile transaction
     *
     * @link http://www.authorize.net/support/AIM_guide.pdf
     * @param Varien_Object $orderPayment
     * @return unknown
     */
    protected function _buildCaptureRequest(Varien_Object $payment, $customerProfileId, $paymentProfileId, $ShippingAddressId) {
        $order = $payment->getOrder();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<transaction>" .
                "<profileTransCaptureOnly>" .
                "<amount>" . $payment->getAmount() . "</amount>"; // should include tax, shipping, and everything.

        if ($order->getTaxAmount()) {
            $content .="<tax>" .
                    "<amount>" . $order->getTaxAmount() . "</amount>" .
                    "<name>Tax</name>" .
                    "<description>Tax</description>" .
                    "</tax>";
        }

        $shipping_method = $order->getShippingMethod();
        if (empty($shipping_method)) {
            $shipping_method = $order->getShippingDescription();
        }

        $content.=
                "<shipping>" .
                "<amount>" . $order->getShippingAmount() . "</amount>" .
                "<name>" . $shipping_method . "</name>" .
                "<description>" . $order->getShippingDescription() . "</description>" .
                "</shipping>";

        if (!empty($order)) {
            $items = $order->getAllItems();
            foreach ($items as $_item) {
                $content .="<lineItems>" .
                        "<itemId>" . $_item->getId() . "</itemId>" .
                        "<name><![CDATA[" . substr($_item->getName(), 0, 30) . "]]></name>" .
                        "<description>Description of item sold</description>" .
                        "<quantity>" . $_item->getQtyOrdered() . "</quantity>" .
                        "<unitPrice>" . $_item->getPrice() . "</unitPrice>" .
                        "<taxable>false</taxable>" .
                        "</lineItems>";
            }
        }
        $content .="<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<customerPaymentProfileId>" . $paymentProfileId . "</customerPaymentProfileId>" .
                "<customerShippingAddressId>" . $ShippingAddressId . "</customerShippingAddressId>";

        if ($order && $order->getIncrementId()) {
            $content .="<order>" .
                    "<invoiceNumber>" . $order->getIncrementId() . "</invoiceNumber>" .
                    "</order>";
        }

        $content .="<taxExempt>false</taxExempt>" .
                "<recurringBilling>false</recurringBilling>" .
                "<cardCode>000</cardCode>" .
                "<approvalCode>000000</approvalCode>" .
                "</profileTransCaptureOnly>" .
                "</transaction>" .
                "</createCustomerProfileTransactionRequest>";
        return $content;
    }

    /**
     * Prepare request to gateway
     *
     * @link http://www.authorize.net/support/CIM_guide.pdf
     * @param Mage_Sales_Model_Document $order
     * @return unknown
     */
    protected function _buildVoidRequest(Varien_Object $payment, $customerProfileId, $paymentProfileId, $ShippingAddressId) {
        $order = $payment->getOrder();
        $CimCollection = Mage::getModel('authorizecim/transaction')->getCollection()
                ->addFieldToFilter('order_payment_id', $payment->getId())
                ->addFieldToFilter('order_id', $order->getId())
                ->getFirstItem();

        $customerProfileId = $CimCollection->getProfileId();
        $paymentProfileId = $CimCollection->getPaymentId();
        $ShippingAddressId = $CimCollection->getShippingId();
        $cardNumber = $CimCollection->getCclast4();
        $transId = $CimCollection->getTxnId();

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<transaction>" .
                "<profileTransVoid>";

        $content .="<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<customerPaymentProfileId>" . $paymentProfileId . "</customerPaymentProfileId>" .
                "<customerShippingAddressId>" . $ShippingAddressId . "</customerShippingAddressId>";

        $content .="<transId>" . $transId . "</transId>" .
                "</profileTransVoid>" .
                "</transaction>" .
                "</createCustomerProfileTransactionRequest>";

        return array('req' => $content, 'cpid' => $customerProfileId, 'cppid' => $paymentProfileId, 'csid' => $ShippingAddressId, 'cc' => $cardNumber);
    }

    /**
     * Prepare request to gateway
     *
     * @link http://www.authorize.net/support/CIM_guide.pdf
     * @param Mage_Sales_Model_Document $order
     * @return unknown
     */
    protected function _buildRefundRequest(Varien_Object $payment, $amount) {
        $order = $payment->getOrder();
        $CimCollection = Mage::getModel('authorizecim/transaction')->getCollection()
                ->addFieldToFilter('order_payment_id', $payment->getId())
                ->addFieldToFilter('order_id', $order->getId());

        if (!$CimCollection->count())
            return array();
        $CimCollection = $CimCollection->getFirstItem();

        $customerProfileId = $CimCollection->getProfileId();
        $paymentProfileId = $CimCollection->getPaymentId();
        $ShippingAddressId = $CimCollection->getShippingId();
        $transId = $CimCollection->getTxnId();
        $card = $this->getPaymentDetail($transId);
        $cardNumber = $card->cardNumber;

        $content = "<?xml version=\"1.0\" encoding=\"utf-8\"?>" .
                "<createCustomerProfileTransactionRequest xmlns=\"AnetApi/xml/v1/schema/AnetApiSchema.xsd\">" .
                $this->merchantAuthenticationBlock() .
                "<transaction>" .
                "<profileTransRefund>";
        $content .="<amount>" . $amount . "</amount>";
        if ($order->getTaxAmount()) {
            $content .="<tax>" .
                    "<amount>" . $order->getTaxAmount() . "</amount>" .
                    "<name>Tax</name>" .
                    "<description>Tax</description>" .
                    "</tax>";
        }

        $shipping_method = $order->getShippingMethod();
        if (empty($shipping_method)) {
            $shipping_method = $order->getShippingDescription();
        }

        $content.="<shipping>" .
                "<amount>" . $order->getShippingAmount() . "</amount>" .
                "<name>" . $shipping_method . "</name>" .
                "<description>" . $order->getShippingDescription() . "</description>" .
                "</shipping>";
        if (!empty($order)) {

            $items = $order->getAllItems();
            if (count($items)) {
                foreach ($items as $_item) {
                    $content .="<lineItems>" .
                            "<itemId>" . $_item->getId() . "</itemId>" .
                            "<name><![CDATA[" . substr($_item->getName(), 0, 30) . "]]></name>" .
                            "<description>Description of item sold</description>" .
                            "<quantity>" . $_item->getQtyOrdered() . "</quantity>" .
                            "<unitPrice>" . $_item->getPrice() . "</unitPrice>" .
                            "</lineItems>";
                }
            }
        }

        $content .="<customerProfileId>" . $customerProfileId . "</customerProfileId>" .
                "<customerPaymentProfileId>" . $paymentProfileId . "</customerPaymentProfileId>" .
                "<customerShippingAddressId>" . $ShippingAddressId . "</customerShippingAddressId>" .
                "<creditCardNumberMasked>" . $cardNumber . "</creditCardNumberMasked>";
        $content .="<order>" .
                "<invoiceNumber>" . $order->getIncrementId() . "</invoiceNumber>" .
                "<description>description of transaction</description>" .
                "<purchaseOrderNumber>" . $order->getIncrementId() . "</purchaseOrderNumber>" .
                "</order>";
        $content .="<transId>" . $transId . "</transId>" .
                "</profileTransRefund>" .
                "</transaction>" .
                "</createCustomerProfileTransactionRequest>";
        return array('req' => $content, 'cpid' => $customerProfileId, 'cppid' => $paymentProfileId, 'csid' => $ShippingAddressId, 'cc' => $cardNumber);
    }

    /**
     * Get config action to process initialization
     *
     * @return string
     */
    public function getConfigPaymentAction() {
        $paymentAction = $this->getConfigData('payment_action');
        return empty($paymentAction) ? true : $paymentAction;
    }

    /**
     * Gateway response wrapper
     *
     * @param string $text
     * @return string
     */
    protected function _wrapGatewayError($text) {
        return Mage::helper('paygate')->__('Gateway error: %s', $text);
    }

    /**
     * Round up and cast specified amount to float or string
     *
     * @param string|float $amount
     * @param bool $asFloat
     * @return string|float
     */
    protected function _formatAmount($amount, $asFloat = false) {
        $amount = sprintf('%.2F', $amount); // "f" depends on locale, "F" doesn't
        return $asFloat ? (float) $amount : $amount;
    }

    /**
     * Add payment transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param string $transactionId
     * @param string $transactionType
     * @param array $transactionDetails
     * @param array $transactionAdditionalInfo
     * @return null|Mage_Sales_Model_Order_Payment_Transaction
     */
    protected function _addTransaction(Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType, array $transactionDetails = array(), array $transactionAdditionalInfo = array(), $message = false
    ) {
        $payment->setTransactionId($transactionId);
        $payment->resetTransactionAdditionalInfo();
        foreach ($transactionDetails as $key => $value) {
            $payment->setData($key, $value);
        }
        foreach ($transactionAdditionalInfo as $key => $value) {
            $payment->setTransactionAdditionalInfo($key, $value);
        }
        $transaction = $payment->addTransaction($transactionType, null, false, $message);
        foreach ($transactionDetails as $key => $value) {
            $payment->unsetData($key);
        }
        $payment->unsLastTransId();

        /**
         * It for self using
         */
        $transaction->setMessage($message);

        return $transaction;
    }

    /**
     * Log debug data to file
     *
     * @param mixed $debugData
     */
    protected function _debug($debugData) {
        if ($this->getDebugFlag()) {
            foreach ($this->_debugReplacePrivateDataKeys as $k) {
                $debugData = preg_replace('/<' . $k . '>.*?<\/' . $k . '>/i', '<' . $k . '>***********</' . $k . '>', $debugData);
            }
            Mage::getModel('core/log_adapter', 'payment_' . $this->getCode() . '.log')
                    ->log($debugData);
        }
    }

}
