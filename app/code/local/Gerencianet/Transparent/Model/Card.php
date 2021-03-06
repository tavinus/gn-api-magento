<?php
/**
 * Gerencianet
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL).
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category   Payment
 * @package    Gerencianet_Transparent
 * @copyright  Copyright (c) 2015 Gerencianet (http://www.gerencianet.com.br)
 * @author     AV5 Tecnologia <anderson@av5.com.br>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Gerencianet_Transparent_Model_Card extends Gerencianet_Transparent_Model_Standard {
	
	/**
	 * Model properties
	 */
	protected $_code 					= 'gerencianet_card';
	protected $_formBlockType 			= 'gerencianet_transparent/card_form';
	protected $_infoBlockType 			= 'gerencianet_transparent/card_info';
	protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = false;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;
	
    /**
     * Assign data to current payment info instance
     *
     * @param $data - paymentData
     *
     * @return Gerencianet_Transparent_Model_Standard
     */
	public function assignData($data) {
		$info = $this->getInfoInstance();
		$quote = Mage::getModel('checkout/session')->getQuote();
		$additionaldata['card']['cc_token'] = $data->getCcToken();
		$additionaldata['card']['cc_installments'] = $data->getCcInstallments();
		
		$info->setAdditionalData(serialize($additionaldata));
		
		$info->setCcType($data->getCcType());
		$info->setCcOwner($data->getCcOwner());
		$info->setCcExpMonth($data->getCcExpMonth());
		$info->setCcExpYear($data->getCcExpYear());
		$info->setCcNumberEnc($info->encrypt($data->getCcNumber()));
		$info->setCcCidEnc($info->encrypt($data->getCcCid()));
		$info->setCcLast4(substr($data->getCcNumber(), -4));
		
		return $this;
	}
	
	/**
	 * Authorizes payment request
	 * 
	 * @param Varien_Object $payment
	 * @param double $amount
	 */
	public function authorize(Varien_Object $payment, $amount)
	{
		if ($this->validateData()) {	
			$pay = $this->payCharge();
			$payData = unserialize($this->getOrder()->getPayment()->getAdditionalData());
			Mage::log('PAY CHARGE CARD: ' . var_export($pay,true),0,'gerencianet.log');
			
		    if ($pay['code'] == 200) {	
    			$payData['charge_id'] = $pay['data']['charge_id'];
    			$payment->setAdditionalData(serialize($payData));
    			$payment->save();
			} else {
			    Mage::throwException($this->_getHelper()->__("Erro no pagamento por cartão!\nMotivo: " . $pay->error_description . ".\nPor favor tente novamente!"));
			}
		}
	}
	
	/**
	 * Returns payment data formatted to API
	 * 
	 * @return array
	 */
	protected function getPaymentData() {
		$payment = unserialize($this->getOrder()->getPayment()->getAdditionalData());
		$return = array( 'credit_card' => array() );
		
		if($payment['card']['cc_installments']) {
			$return['credit_card']['installments'] = (int)$payment['card']['cc_installments'];
		}
		
		if($payment['card']['cc_token']) {
			$return['credit_card']['payment_token'] = $payment['card']['cc_token'];
		}
		
		$return['credit_card']['billing_address'] = $this->getBillingAddress();
		$return['credit_card']['customer'] = $this->getCustomer();
		
		$return['credit_card'] = $this->checkDiscount($return['credit_card']);
		
		return $return;
	}
}