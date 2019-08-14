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
class Sttl_Authorizecim_Model_Payment extends Sttl_Authorizecim_Model_Api {

    const XML_PAYMENT_ACTION = 'payment/authorizecim/payment_action';
    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';
    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_VOID = 'VOID';
    const REQUEST_TYPE_CREDIT = 'CREDIT';
    const RESPONSE_RESULT_CODE = 'Ok';

    protected $_formBlockType = 'authorizecim/form_cim';
    protected $_infoBlockType = 'authorizecim/info_cim';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = false;
    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;
    protected $_canSaveCc = false;

    /**
     * Can be edit order (renew order)
     *
     * @return bool
     */
    public function canEdit() {
        return false;
    }

    public function isAvailable($quote = null) {
        $useGuest = $this->getConfigData('use_guest');
        if (!$useGuest) {
            $checkoutMethod = $quote->getCheckoutMethod();
            if ($checkoutMethod == Mage_Checkout_Model_Type_Onepage::METHOD_GUEST) {
                return false;
            }
        }
        return parent::isAvailable($quote);
    }

    /**
     * Authorize
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_GoogleCheckout_Model_Payment
     */
    public function authorize(Varien_Object $payment, $amount) {
        $isNew = $_paymentProfileId = $message = null;
        $order = $payment->getOrder();
        $total = $order->getBaseGrandTotal();
        $amount = $total; //+$amount;

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for authorization.'));
        }
        $payment->setAmount($amount);

        $payment_values = Mage::app()->getRequest()->getParam('payment');
        if (array_key_exists('cim_payment_profileid', $payment_values)) {
            $cim_payment_profileid = $payment_values['cim_payment_profileid'];
            if (!empty($cim_payment_profileid)) {
                $_paymentProfileId = $cim_payment_profileid;
            }
        }

        $billing = $order->getBillingAddress();
        if (!$billing->getEmail() && Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $billing->setEmail($customer->getEmail());
        }
        $cimCollection = Mage::getModel('authorizecim/transaction')->getCollection()
                ->addFieldToFilter('email', $billing->getEmail());
        if ($cimCollection->getSize()) {
            $customerColection = $cimCollection->getFirstItem();            
            $customerProfileId = $customerColection->getProfileId();
            $payment->setCimCustomerProfileid($customerProfileId);
        } else {
            $customerrequest = $this->_buildCustomerRequest($payment);
            $customerresponse = $this->sendRequestViaCurl($customerrequest);
            $parsedresponse = simplexml_load_string($customerresponse, "SimpleXMLElement", LIBXML_NOWARNING);

            if (self::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
                $message = Mage::helper('paygate')->__("The operation failed with the following errors: ");
                foreach ($parsedresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($parsedresponse->messages->message)){
                    $message = strip_tags($customerresponse);
                }
            } else {
                $isNew = true;
                $customerProfileId = $parsedresponse->customerProfileId;
                $payment->setCimCustomerProfileid($customerProfileId);
            }
            if ($message) {
                Mage::throwException($message);
            }
        }

        if ($_paymentProfileId) {
            $paymentProfile = $_paymentProfileId;
        } else {
            $paymentProfile = $this->checkPaymentProfile($customerProfileId, $payment);
        }
        /* Payment Profile */
        if (!$paymentProfile) {
            $request = $this->_buildPaymentRequest($payment, $customerProfileId);
            $Paymentresponse = $this->sendRequestViaCurl($request);
            $parsedPaymentresponse = simplexml_load_string($Paymentresponse, "SimpleXMLElement", LIBXML_NOWARNING);
            //$parsedPaymentresponse = simplexml_load_string($Paymentresponse);
            if (self::RESPONSE_RESULT_CODE != $parsedPaymentresponse->messages->resultCode) {
                $message = Mage::helper('paygate')->__("The operation failed with the following errors: ");
                foreach ($parsedPaymentresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($parsedPaymentresponse->messages->message)){
                    $message = strip_tags($Paymentresponse);
                }
            } else {
                $paymentProfileId = $parsedPaymentresponse->customerPaymentProfileId;
                $payment->setCimPaymentProfileid($paymentProfileId);
            }
            if ($message) {
                $payment->setSkipTransactionCreation(true);
                $this->_deleteCustomerProfile($customerProfileId, $isNew);
                Mage::throwException($message);
            }
        } else {
            $payment->setCimPaymentProfileid($paymentProfile);
            $paymentProfileId = $paymentProfile;
        }
        
        /* Shipping Profile */
        $shippingProfile = $this->checkShippingProfile($customerProfileId);

        if (!$shippingProfile) {
            $ShippingRequest = $this->_buildShippingRequest($payment, $customerProfileId);
            $ShippingResponse = $this->sendRequestViaCurl($ShippingRequest);
            $parsedShippingresponse = simplexml_load_string($ShippingResponse, "SimpleXMLElement", LIBXML_NOWARNING);
            //$parsedShippingresponse = simplexml_load_string($ShippingResponse);

            if (self::RESPONSE_RESULT_CODE != $parsedShippingresponse->messages->resultCode) {
                $message = Mage::helper('paygate')->__("The operation failed with the following errors: ");
                foreach ($parsedShippingresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($parsedShippingresponse->messages->message)){
                    $message = strip_tags($ShippingResponse);
                }
            } else {
                $ShippingAddressId = $parsedShippingresponse->customerAddressId;
                $payment->setCimShippingAddressid($ShippingAddressId);
            }
            if ($message) {
                $payment->setSkipTransactionCreation(true);
                $this->_deleteCustomerProfile($customerProfileId, $isNew);
                Mage::throwException($message);
            }
        } else {
            $payment->setCimShippingAddressid($shippingProfile);
            $ShippingAddressId = $shippingProfile;
        }

        /* Transaction Profile */
        $transactionAction = Mage::getStoreConfig(self::XML_PAYMENT_ACTION);
        if ($transactionAction == self::ACTION_AUTHORIZE) {
            $TransactionRequest = $this->_buildTransactionRequest($payment, $customerProfileId, $paymentProfileId, $ShippingAddressId);
            $TransactionResponse = $this->sendRequestViaCurl($TransactionRequest);
            $parsedresponse = simplexml_load_string($TransactionResponse, "SimpleXMLElement", LIBXML_NOWARNING);

            if (self::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
                $payment->setSkipTransactionCreation(true);
                $errorText = $parsedresponse->messages->message->code . "-" . $parsedresponse->messages->message->text;
                $this->_deleteCustomerProfile($customerProfileId, $isNew);
                Mage::throwException($this->_wrapGatewayError($errorText));
                return $this;
            }
            if (isset($parsedresponse->directResponse)) {
                $directResponseFields = explode(",", $parsedresponse->directResponse);
                $responseCode = $directResponseFields[0]; // 1 = Approved 2 = Declined 3 = Error
                if ($responseCode != 1) {
                    $errorText = $parsedresponse->messages->message->code . "-" . $parsedresponse->messages->message->text;
                    $this->_deleteCustomerProfile($customerProfileId, $isNew);
                    Mage::throwException($this->_wrapGatewayError($errorText));
                    return $this;
                }
                $responseReasonCode = $directResponseFields[2]; // See http://www.authorize.net/support/AIM_guide.pdf
                $responseReasonText = $directResponseFields[3];
                $approvalCode = $directResponseFields[4]; // Authorization code

                $cclast4 = $directResponseFields[50]; // CC last 4 digits
                $cctype = $directResponseFields[51]; // CC type

                $transId = $directResponseFields[6];
                $transId = htmlspecialchars($transId);
                $payment->setCimTransactionid($transId);
                $payment->setTransactionId($transId);
                $payment->setIsTransactionClosed(0);
            }
        } else {
            $TransactionRequest = $this->_buildTransactionCaptureRequest($payment, $customerProfileId, $paymentProfileId, $ShippingAddressId);
            $TransactionResponse = $this->sendRequestViaCurl($TransactionRequest);
            $parsedresponse = simplexml_load_string($TransactionResponse, "SimpleXMLElement", LIBXML_NOWARNING);
            //$parsedresponse = simplexml_load_string($TransactionResponse);
            if (self::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
                $payment->setSkipTransactionCreation(true);
                $errorText = $parsedresponse->messages->message->code . "-" . $parsedresponse->messages->message->text;
                $this->_deleteCustomerProfile($customerProfileId, $isNew);
                Mage::throwException($this->_wrapGatewayError($errorText));
                return $this;
            }
            if (isset($parsedresponse->directResponse)) {
                $directResponseFields = explode(",", $parsedresponse->directResponse);
                $responseCode = $directResponseFields[0]; // 1 = Approved 2 = Declined 3 = Error
                if ($responseCode != 1) {
                    $errorText = $parsedresponse->messages->message->code . "-" . $transresponse->messages->message->text;
                    $this->_deleteCustomerProfile($customerProfileId, $isNew);
                    Mage::throwException($this->_wrapGatewayError($errorText));
                    return $this;
                }
                $responseReasonCode = $directResponseFields[2]; // See http://www.authorize.net/support/AIM_guide.pdf
                $responseReasonText = $directResponseFields[3];
                $approvalCode = $directResponseFields[4]; // Authorization code

                $transId = $directResponseFields[6];
                $transId = htmlspecialchars($transId);

                $cclast4 = $directResponseFields[50]; // CC last 4 digits
                $cctype = $directResponseFields[51]; // CC type

                $payment->setCimTransactionid($transId);
                $payment->setTransactionId($transId);
                $payment->setIsTransactionClosed(0);
            }
        }
        $cust_email = $billing->getEmail();
        if (!empty($cust_email)) {
            $customer_email = $cust_email;
        } else {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $billingAddress = $quote->getBillingAddress();
            $customer_email = $billingAddress->getEmail();
        }

        /*  transaction save */
        $data = array('order_id' => $order->getId(), 'email' => $customer_email, 'cclast4' => $cclast4,
            'card_type' => $cctype, 'order_payment_id' => $payment->getId(), 'profile_id' => $customerProfileId,
            'payment_id' => $paymentProfileId, 'shipping_id' => $ShippingAddressId, 'txn_id' => $transId, 'response' => $TransactionResponse);
        $this->saveTransactionDetails($data);
        return $this;
    }

    /* Send capture request to gateway

     *
     * @param Varien_Object $payment
     * @param decimal $amount
     * @return Mage_Paygate_Model_Authorizenet
     * @throws Mage_Core_Exception
     */

    public function capture(Varien_Object $payment, $amount) {
        $transaction_details = $payment->getAuthorizationTransaction();
        if (!empty($transaction_details)) {
            $order_id = $transaction_details->getOrderId();
            $transModel = Mage::getModel('authorizecim/transaction')
                    ->getCollection()
                    ->addFieldToFilter('order_id', $order_id);

            if ($transModel->getSize()) {
                $trandaction_profile = $transModel->getFirstItem();
                $customer_profile_id = $trandaction_profile->getProfileId();
                $customer_payment_id = $trandaction_profile->getPaymentId();
                $customer_shipping_id = $trandaction_profile->getShippingId();
                $payment->setAnetTransType(self::REQUEST_TYPE_AUTH_CAPTURE);
                $order = $payment->getOrder();
                $billing = $order->getBillingAddress();
                $payment->setAmount($amount);
                $TransactionRequest = $this->_buildCaptureRequest($payment, $customer_profile_id, $customer_payment_id, $customer_shipping_id);
                $TransactionResponse = $this->sendRequestViaCurl($TransactionRequest);
                $transresponse = simplexml_load_string($TransactionResponse, "SimpleXMLElement", LIBXML_NOWARNING);
                if (isset($transresponse->directResponse)) {
                    $directResponseFields = explode(",", $transresponse->directResponse);
                    $responseCode = $directResponseFields[0]; // 1 = Approved 2 = Declined 3 = Error
                    if ($responseCode == 1) {
                        $responseReasonCode = $directResponseFields[2]; // See http://www.authorize.net/support/AIM_guide.pdf
                        $responseReasonText = $directResponseFields[3];
                        $approvalCode = $directResponseFields[4]; // Authorization code
                        $transId = $directResponseFields[6];
                        $transId = htmlspecialchars($transId);

                        $cclast4 = $directResponseFields[50];
                        $cardType = $directResponseFields[51];

                        $payment->setStatus(self::STATUS_APPROVED);
                        $payment->setLastTransId($transId);
                        if (!$payment->getParentTransactionId() || $transId != $payment->getParentTransactionId()) {
                            $payment->setTransactionId($transId);
                            $payment->setIsTransactionClosed(0);
                        }
                        $payment->setCimTransactionid($transId);

                        /*  transaction save */
                        $data = array('order_id' => $order->getId(), 'email' => $billing->getEmail(), 'cclast4' => $cclast4,
                            'card_type' => $cardType, 'order_payment_id' => $payment->getId(), 'profile_id' => $customer_profile_id,
                            'payment_id' => $customer_payment_id, 'shipping_id' => $customer_shipping_id, 'txn_id' => $transId, 'response' => $TransactionResponse);
                        $this->saveTransactionDetails($data);
                    }

                    if (self::RESPONSE_RESULT_CODE != $transresponse->messages->resultCode) {
                        $payment->setSkipTransactionCreation(true);
                        $errorText = $transresponse->messages->message->code . "-" . $transresponse->messages->message->text;
                        Mage::throwException($this->_wrapGatewayError($errorText));
                    }
                } else {
                    $payment->setSkipTransactionCreation(true);
                    Mage::throwException(Mage::helper('paygate')->__('Error in capturing the payment.'));
                }
            } else {
                //Authorize Capture
                $this->authorize_capture($payment, $amount);
            }
        } else {
            //Authorize Capture
            $this->authorize_capture($payment, $amount);
        }
    }

    protected function authorize_capture(Varien_Object $payment, $amount) {
        $isNew = $message = null;
        $order = $payment->getOrder();
        $total = $order->getBaseGrandTotal();
        $amount = $total; //+$amount;

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('paygate')->__('Invalid amount for authorization.'));
        }
        $payment->setAmount($amount);

        $payment_values = Mage::app()->getRequest()->getParam('payment');
        if (array_key_exists('cim_payment_profileid', $payment_values)) {

            $cim_payment_profileid = $payment_values['cim_payment_profileid'];
            if (!empty($cim_payment_profileid)) {
                $_paymentProfileId = $cim_payment_profileid;
            }
        }

        $billing = $order->getBillingAddress();
        if (!$billing->getEmail() && Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            $billing->setEmail($customer->getEmail());
        }
        $cimCollection = Mage::getModel('authorizecim/transaction')->getCollection()
                ->addFieldToFilter('email', $billing->getEmail());
        if ($cimCollection->getSize()) {
            $customerColection = $cimCollection->getFirstItem();
            $customerProfileId = $customerColection->getProfileId();
            $payment->setCimCustomerProfileid($customerProfileId);
        } else {
            $customerrequest = $this->_buildCustomerRequest($payment);
            $customerresponse = $this->sendRequestViaCurl($customerrequest);
            
            $parsedresponse = simplexml_load_string($customerresponse, "SimpleXMLElement", LIBXML_NOWARNING);

            if (self::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
                $payment->setSkipTransactionCreation(true);
                $message = Mage::helper('paygate')->__('The operation failed with the following errors: ');
                foreach ($parsedresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($parsedresponse->messages->message)){
                    $message = strip_tags($customerresponse);
                }
            } else {
                $isNew = true;
                $customerProfileId = $parsedresponse->customerProfileId;
                $payment->setCimCustomerProfileid($customerProfileId);
            }
            if ($message) {
                $payment->setSkipTransactionCreation(true);                
                Mage::throwException($message);
            }
        }

        if (!empty($_paymentProfileId)) {
            $paymentProfile = $_paymentProfileId;
        } else {
            $paymentProfile = $this->checkPaymentProfile($customerProfileId, $payment);
        }

        /* Payment Profile */
        if (!$paymentProfile) {
            $request = $this->_buildPaymentRequest($payment, $customerProfileId);
            $Paymentresponse = $this->sendRequestViaCurl($request);
            $parsedPaymentresponse = simplexml_load_string($Paymentresponse, "SimpleXMLElement", LIBXML_NOWARNING);
            if (self::RESPONSE_RESULT_CODE != $parsedPaymentresponse->messages->resultCode) {
                $message = Mage::helper('paygate')->__('The operation failed with the following errors: ');
                foreach ($parsedPaymentresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($parsedPaymentresponse->messages->message)){
                    $message = strip_tags($Paymentresponse);
                }
            } else {
                $paymentProfileId = $parsedPaymentresponse->customerPaymentProfileId;
                $payment->setCimPaymentProfileid($paymentProfileId);
            }
            if ($message) {
                $payment->setSkipTransactionCreation(true);
                $this->_deleteCustomerProfile($customerProfileId, $isNew);
                Mage::throwException($message);
            }
        } else {
            $payment->setCimPaymentProfileid($paymentProfile);
            $paymentProfileId = $paymentProfile;
        }

        /* Shipping Profile */
        $shippingProfile = $this->checkShippingProfile($customerProfileId);

        if (!$shippingProfile) {
            $ShippingRequest = $this->_buildShippingRequest($payment, $customerProfileId);
            $ShippingResponse = $this->sendRequestViaCurl($ShippingRequest);
            $parsedShippingresponse = simplexml_load_string($ShippingResponse, "SimpleXMLElement", LIBXML_NOWARNING);

            if (self::RESPONSE_RESULT_CODE != $parsedShippingresponse->messages->resultCode) {
                $message = Mage::helper('paygate')->__('The operation failed with the following errors: ');
                foreach ($parsedShippingresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($parsedShippingresponse->messages->message)){
                    $message = strip_tags($ShippingResponse);
                }
            } else {
                $ShippingAddressId = $parsedShippingresponse->customerAddressId;
                $payment->setCimShippingAddressid($ShippingAddressId);
            }
            if ($message) {
                $payment->setSkipTransactionCreation(true);
                $this->_deleteCustomerProfile($customerProfileId, $isNew);
                Mage::throwException($message);
            }
        } else {
            $payment->setCimShippingAddressid($shippingProfile);
            $ShippingAddressId = $shippingProfile;
        }

        $TransactionRequest = $this->_buildTransactionCaptureRequest($payment, $customerProfileId, $paymentProfileId, $ShippingAddressId);
        $TransactionResponse = $this->sendRequestViaCurl($TransactionRequest);
        $parsedresponse = simplexml_load_string($TransactionResponse, "SimpleXMLElement", LIBXML_NOWARNING);
        if (self::RESPONSE_RESULT_CODE != $parsedresponse->messages->resultCode) {
            $errorText = $parsedresponse->messages->message->code . "-" . $parsedresponse->messages->message->text;
            $this->_deleteCustomerProfile($customerProfileId, $isNew);
            Mage::throwException($this->_wrapGatewayError($errorText));
            return $this;
        }

        if (isset($parsedresponse->directResponse)) {
            $directResponseFields = explode(",", $parsedresponse->directResponse);
            $responseCode = $directResponseFields[0]; // 1 = Approved 2 = Declined 3 = Error

            if ($responseCode != 1) {
                $payment->setSkipTransactionCreation(true);
                $errorText = $parsedresponse->messages->message->code . "-" . $parsedresponse->messages->message->text;
                $this->_deleteCustomerProfile($customerProfileId, $isNew);
                Mage::throwException($this->_wrapGatewayError($errorText));
                return $this;
            }

            $responseReasonCode = $directResponseFields[2]; // See http://www.authorize.net/support/AIM_guide.pdf
            $responseReasonText = $directResponseFields[3];
            $approvalCode = $directResponseFields[4]; // Authorization code
            $transId = $directResponseFields[6];
            $transId = htmlspecialchars($transId);

            $cclast4 = $directResponseFields[50];
            $cardType = $directResponseFields[51];

            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setLastTransId($transId);
            $payment->setCimTransactionid($transId);
            $payment->setTransactionId($transId);
            $payment->setIsTransactionClosed(0);

            $cust_email = $billing->getEmail();
            if (!empty($cust_email)) {
                $customer_email = $cust_email;
            } else {
                $quote = Mage::getSingleton('checkout/session')->getQuote();
                $billingAddress = $quote->getBillingAddress();
                $customer_email = $billingAddress->getEmail();
            }

            /*  transaction save */
            $data = array('order_id' => $order->getId(), 'email' => $customer_email, 'cclast4' => $cclast4,
                'card_type' => $cardType, 'order_payment_id' => $payment->getId(), 'profile_id' => $customerProfileId,
                'payment_id' => $paymentProfileId, 'shipping_id' => $ShippingAddressId, 'txn_id' => $transId, 'response' => $TransactionResponse);
            $this->saveTransactionDetails($data);
        }
        return $this;
    }

    /**
     * Void the payment through gateway
     *
     * @param Varien_Object $payment
     * @return Mage_Paygate_Model_Authorizenet
     * @throws Mage_Core_Exception
     */
    public function void(Varien_Object $payment) {
        if ($payment->getParentTransactionId()) {
            $payment->setAnetTransType(self::REQUEST_TYPE_VOID);

            $TransactionRequestArr = $this->_buildVoidRequest($payment, $payment->getCimCustomerProfileid(), $payment->getCimPaymentProfileid(), $payment->getCimShippingAddressid());
            if (is_array($TransactionRequestArr) && count($TransactionRequestArr) > 0) {
                $TransactionRequest = $TransactionRequestArr['req'];
                $TransactionResponse = $this->sendRequestViaCurl($TransactionRequest);
                $transresponse = simplexml_load_string($TransactionResponse, "SimpleXMLElement", LIBXML_NOWARNING);
                if (self::RESPONSE_RESULT_CODE != $transresponse->messages->resultCode) {
                    $message = Mage::helper('paygate')->__("The operation failed with the following errors: ");
                    foreach ($transresponse->messages->message as $msg) {
                        $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                    }
                    if(!count($transresponse->messages->message)){
                        $message = strip_tags($TransactionResponse);
                    }
                } else {
                    if (isset($transresponse->directResponse)) {
                        $directResponseFields = explode(",", $transresponse->directResponse);
                        $responseCode = $directResponseFields[0]; // 1 = Approved 2 = Declined 3 = Error
                        if ($responseCode == 1) {
                            $transId = $directResponseFields[6];
                            $transId = htmlspecialchars($transId);

                            $cclast4 = $directResponseFields[50];
                            $cardType = $directResponseFields[51];

                            $payment->setLastTransId($transId);
                            $payment->setIsTransactionClosed(1);
                            $order = $payment->getOrder();
                            $billing = $order->getBillingAddress();
                            $customer_profile_id = $TransactionRequestArr['cpid'];
                            $customer_payment_id = $TransactionRequestArr['cppid'];
                            $customer_shipping_id = $TransactionRequestArr['csid'];

                            /*  transaction save */
                            $data = array('order_id' => $order->getId(), 'email' => $billing->getEmail(), 'cclast4' => $cclast4,
                                'card_type' => $cardType, 'order_payment_id' => $payment->getId(), 'profile_id' => $customer_profile_id,
                                'payment_id' => $customer_payment_id, 'shipping_id' => $customer_shipping_id, 'txn_id' => $transId, 'response' => $TransactionResponse);
                            $this->saveTransactionDetails($data);

                            $payment->setStatus(self::STATUS_SUCCESS);
                            return $this;
                        }
                    }
                    $payment->setSkipTransactionCreation(true);
                    $payment->setStatus(self::STATUS_ERROR);
                    $errorText = $transresponse->messages->message->code . "-" . $transresponse->messages->message->text;
                    Mage::throwException($errorText);
                }
            } else {
                $payment->setSkipTransactionCreation(true);
                $payment->setStatus(self::STATUS_ERROR);
                Mage::throwException(Mage::helper('paygate')->__('Transaction not found.'));
            }
        } else {
            $payment->setSkipTransactionCreation(true);
            $payment->setStatus(self::STATUS_ERROR);
            Mage::throwException(Mage::helper('paygate')->__('Invalid transaction ID.'));
        }
    }

    public function cancel(Varien_Object $payment) {
        return $this->void($payment);
    }

    /**
     * Refund the amount
     * Need to decode Last 4 digits for request.
     *
     * @param Varien_Object $payment
     * @param decimal $amount
     * @return Mage_Authorizenet_Model_Directpost
     * @throws Mage_Core_Exception
     */
    public function refund(Varien_Object $payment, $amount) {
        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }
        if ($payment->getParentTransactionId()) {
            $order = $payment->getOrder();
            $payment->setAnetTransType(self::REQUEST_TYPE_CREDIT);
            $TransactionRequestArr = $this->_buildRefundRequest($payment, $amount);
            $TransactionRequest = $TransactionRequestArr['req'];
            $TransactionResponse = $this->sendRequestViaCurl($TransactionRequest);
            $transresponse = simplexml_load_string($TransactionResponse, "SimpleXMLElement", LIBXML_NOWARNING);
            if (self::RESPONSE_RESULT_CODE != $transresponse->messages->resultCode) {
                $message = Mage::helper('paygate')->__("The operation failed with the following errors: ");
                foreach ($transresponse->messages->message as $msg) {
                    $message .= Mage::helper('paygate')->__("[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text));
                }
                if(!count($transresponse->messages->message)){
                    $message = strip_tags($TransactionResponse);
                }
            } else {
                if (isset($transresponse->directResponse)) {
                    $directResponseFields = explode(",", $transresponse->directResponse);
                    $responseCode = $directResponseFields[0]; // 1 = Approved 2 = Declined 3 = Error
                    if ($responseCode == 1) {
                        $transId = $directResponseFields[6];
                        $transId = htmlspecialchars($transId);

                        $cclast4 = $directResponseFields[50];
                        $cardType = $directResponseFields[51];
                        /**
                         * If it is last amount for refund, transaction with type "capture" will be closed
                         * and card will has last transaction with type "refund"
                         */
                        $payment->setLastTransId($transId);
                        if (!$payment->getParentTransactionId() || $transId != $payment->getParentTransactionId()) {
                            $payment->setTransactionId($transId);
                        }
                        $payment->setIsTransactionClosed(0);

                        if ($this->_formatAmount($order->getTotalRefunded()) == $this->_formatAmount($order->getTotalInvoiced())) {
                            $payment->setIsTransactionClosed(1);
                        }
                        $payment->setStatus(self::STATUS_SUCCESS);
                        $billing = $order->getBillingAddress();

                        /*  transaction save */
                        $data = array('order_id' => $order->getId(), 'email' => $billing->getEmail(), 'cclast4' => $cclast4,
                            'card_type' => $cardType, 'order_payment_id' => $payment->getId(), 'profile_id' => $TransactionRequestArr['cpid'],
                            'payment_id' => $TransactionRequestArr['cppid'], 'shipping_id' => $TransactionRequestArr['csid'], 'txn_id' => $transId, 'response' => $TransactionResponse);
                        $this->saveTransactionDetails($data);

                        return $this;
                    }
                }
            }
            $payment->setSkipTransactionCreation(true);
            $payment->setStatus(self::STATUS_ERROR);
            $errorText = $transresponse->messages->message->code . "-" . $transresponse->messages->message->text;
            Mage::throwException(Mage::helper('paygate')->__($errorText));
            return $this;
        } else {
            $payment->setSkipTransactionCreation(true);
            $payment->setStatus(self::STATUS_ERROR);
            Mage::throwException(Mage::helper('paygate')->__('Invalid transaction ID.'));
            return $this;
        }
    }

    /*  save authorizecim transaction details   */

    protected function saveTransactionDetails($data) {
        $transModel = Mage::getModel('authorizecim/transaction');
        $transModel->setData($data)
                ->setCreated(Mage::getModel('core/date')->date())
                ->save();        
        return $transModel->getId();
    }

}
