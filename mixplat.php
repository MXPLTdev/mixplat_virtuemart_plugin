<?php

if (!defined('_VALID_MOS') && !defined('_JEXEC')) {
	die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');
}

if (!class_exists('vmPSPlugin')) {
	require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}
require_once __DIR__ . '/mixplat/lib.php';

class plgVmPaymentMixplat extends vmPSPlugin
{
	private $method = null;

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		$this->_loggable   = true;
		$this->tableFields = array_keys($this->getTableSQLFields());
		if (version_compare(JVM_VERSION, '3', 'ge')) {
			$varsToPush = $this->getVarsToPush();
		} else {
			$varsToPush = array(
				'payment_logos'             => array('', 'char'),
				'countries'                 => array(0, 'int'),
				'categories'                => array(0, 'int'),
				'payment_order_total'       => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\' ',
				'payment_currency'          => array(0, 'int'),
				'min_amount'                => array(0, 'int'),
				'max_amount'                => array(0, 'int'),
				'cost_per_transaction'      => array(0, 'int'),
				'cost_percent_total'        => array(0, 'int'),
				'tax_id'                    => array(0, 'int'),
				'api_key'                   => array('', 'string'),
				'project_id'                => array('', 'string'),
				'form_id'                   => array('', 'string'),
				'status_success'            => array('U', 'string'),
				'order_status'              => array('P', 'string'),
				'status_pending'            => array('P', 'string'),
				'status_for_payment'        => array('P', 'string'),
				'payment_message'           => array('', 'string'),
				'payment_type'              => array('0', 'string'),
				'hold'                      => array('', 'string'),
				'test_mode'                 => array('', 'string'),
				'allowAll'                  => array('', 'string'),
				'mixplatIpList'             => array('', 'string'),
				'confirm_status'            => array('C', 'string'),
				'cancel_status'             => array('X', 'string'),
				'sendReceipt'               => array('', 'string'),
				'tax_system'                => array('', 'string'),
				'tax'                       => array('', 'string'),
				'tax_delivery'              => array('', 'string'),
				'paymentSubjectType'        => array('', 'string'),
				'paymentMethodType'         => array('', 'string'),
				'deliveryPaymentMethodType' => array('', 'string'),
				'printSecondCheck'          => array('', 'string'),
				'secondReceiptStatus'       => array('S', 'string'),
			);
		}
		$this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
	}

	protected function getVmPluginCreateTableSQL()
	{
		return $this->createTableSQL('Payment Mixplat Table');
	}

	public function plgVmDeclarePluginParamsPaymentVM3(&$data)
	{
		return $this->declarePluginParams('payment', $data);
	}

	public function getTableSQLFields()
	{
		$SQLfields = array(
			'id'                          => 'int(11) unsigned NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(11) UNSIGNED',
			'order_number'                => 'char(32)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000) NOT NULL DEFAULT \'\'',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'cost_per_transaction'        => ' decimal(10,2)',
			'cost_percent_total'          => ' decimal(10,2)',
			'tax_id'                      => 'smallint(11)',
			'payment_id'                  => ' varchar(36)',
			'return_id'                   => ' varchar(39)',
			'status'                      => ' varchar(20)',
			'status_extended'             => ' varchar(30)',
		);
		return $SQLfields;
	}

	public function plgVmConfirmedOrder($cart, $order)
	{

		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return null;
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->method = $method;
		$lang         = JFactory::getLanguage();
		$filename     = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);
		$vendorId = 0;

		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists('VirtueMartModelOrders')) {
			require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}

		if (!$method->payment_currency) {
			$this->getPaymentCurrency($method);
		}

		// END printing out HTML Form code (Payment Extra Info)
		$q  = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
		$db = JFactory::getDBO();
		$db->setQuery($q);
		$currency_code_3        = $db->loadResult();
		$paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = $paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false);
		if (method_exists($paymentCurrency, "roundForDisplay")) {
			$totalInPaymentCurrency = number_format($paymentCurrency->roundForDisplay($totalInPaymentCurrency, $method->payment_currency), 2, '.', '');
		} else {
			$totalInPaymentCurrency = number_format(round($totalInPaymentCurrency, 2), 2, '.', '');
		}
		$cd                                      = CurrencyDisplay::getInstance($cart->pricesCurrency);
		$virtuemart_order_id                     = VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
		$html                                    = '';
		$this->_virtuemart_paymentmethod_id      = $order['details']['BT']->virtuemart_paymentmethod_id;
		$dbValues['payment_name']                = $this->renderPluginName($method);
		$dbValues['order_number']                = $order['details']['BT']->order_number;
		$dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
		$dbValues['cost_per_transaction']        = $method->cost_per_transaction;
		$dbValues['cost_percent_total']          = $method->cost_percent_total;
		$dbValues['payment_currency']            = $currency_code_3;
		$dbValues['payment_order_total']         = $totalInPaymentCurrency;
		$dbValues['tax_id']                      = $method->tax_id;

		$redirect    = true;
		$printButton = $method->status_for_payment == $method->order_status;

		$paymentId = $this->printButton($html, $totalInPaymentCurrency, $order, $redirect, $printButton);
		if (!$paymentId) {
			return $this->processConfirmedOrderPaymentResponse(false, $cart, $order, $html, $this->renderPluginName($method, $order), $method->order_status);
		}
		$dbValues['payment_id'] = $paymentId;
		$this->storePSPluginInternalData($dbValues);
		$modelOrder                 = VmModel::getModel('orders');
		$order['order_status']      = $method->order_status;
		$order['customer_notified'] = 1;
		$order['comments']          = '';
		$modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);
		$cart->orderdoneHtml = $html;
		$cart->emptyCart();
		JRequest::setVar('html', $html);
		return true;
	}

	public function printButton(&$html, $totalInPaymentCurrency, $order, $redirect = 0, $print_button = 1)
	{
		$returnValue  = false;
		$order_number = $order['details']['BT']->order_number;
		if ($print_button) {
			$data = array(
				'amount'              => intval($totalInPaymentCurrency * 100),
				'test'                => $this->method->test_mode,
				'project_id'          => $this->method->project_id,
				'payment_form_id'     => $this->method->form_id,
				'request_id'          => MixplatLib::getIdempotenceKey(),
				'merchant_payment_id' => $order['details']['BT']->virtuemart_order_id,
				'user_email'          => $order['details']['BT']->email,
				'url_success'         => JUri::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pass=' . $order['details']['BT']->order_pass . '&Itemid=' . JRequest::getInt('Itemid') . '&lang=' . JRequest::getCmd('lang', ''),
				'url_failure'         => JURI::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pass=' . $order['details']['BT']->order_pass . '&Itemid=' . JRequest::getInt('Itemid') . '&lang=' . JRequest::getCmd('lang', ''),
				'notify_url'          => JUri::root() . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pelement=mixplat',
				'payment_scheme'      => $this->method->hold,
				'description'         => $this->getPaymentDescription($order),
			);
			if ($this->method->payment_type != '0') {
				$data['payment_method'] = $this->method->payment_type;
			}
			if ($this->method->sendReceipt) {
				$data['items'] = $this->getReceiptItems($order, $totalInPaymentCurrency);
			}

			$data['signature'] = MixplatLib::calcPaymentSignature($data, $this->method->api_key);

			try {
				$result = MixplatLib::createPayment($data);
				file_put_contents(__DIR__ . '/log.txt', var_export([$data, $result], true), FILE_APPEND);
				$returnValue = $result->payment_id;
				$html .= '<form method="get" action="' . $result->redirect_url . '" name="vm_mixplat_form">';

				if ($redirect == 0) {
					$html .= "<input type='submit' name='pay' value='Перейти к оплате' class='vm_mixplat_button'>";
				}
				$html .= '</form>';
				if ($redirect == 1) {
					$html .= 'Сейчас вы будете перемещены на страницу оплаты';
					$html .= ' <script type="text/javascript">';
					$html .= ' setTimeout(function(){document.forms.vm_mixplat_form.submit();},2000);';
					$html .= ' </script>';
				}
			} catch (Exception $e) {
				$html .= $e->getMessage();
				return false;
			}
		} else {
			if ($order['details']['BT']->order_status == 'P' || $order['details']['BT']->order_status == 'U') {
				$html .= $this->method->payment_message;
			}
		}
		return $returnValue;
	}

	private function getPaymentDescription($order)
	{
		$description = str_replace(
			array('%order_number%', '%email%'),
			array($order['details']['BT']->order_number, $order['details']['BT']->email),
			$this->method->paymentDescription);
		return $description;
	}

	private function getReceiptItems($order, $total)
	{
		$items       = [];
		$paymentMode = $this->method->paymentMethodType;
		if ($secondReceipt) {
			$paymentMode = 'full_payment';
		}
		foreach ($order['items'] as $item) {
			$items[] = array(
				"name"     => $item->order_item_name,
				"quantity" => $item->product_quantity,
				"sum"      => round($item->product_final_price * $item->product_quantity * 100),
				"vat"      => $this->method->tax,
				"method"   => $paymentMode,
				"object"   => $this->method->paymentSubjectType,
			);
		}

		$shipping = $order['details']['BT']->order_shipment + $order['details']['BT']->order_shipment_tax;

		if ($shipping > 0) {
			$items[] = array(
				"name"     => 'Доставка',
				"quantity" => '1',
				"sum"      => round($shipping * 100),
				"vat"      => $this->method->tax_delivery,
				"method"   => $this->method->paymentMethodType,
				"object"   => $this->method->deliveryPaymentSubjectType,
			);
		}

		$total = intval($total * 100);

		$items = MixplatLib::normalizeReceiptItems($items, $total);
		return $items;
	}

	public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
	{
		if (!$this->selectedThisByMethodId($virtuemart_payment_id)) {
			return null; // Another method was selected, do nothing
		}

		$db = JFactory::getDBO();
		$q  = 'SELECT * FROM `' . $this->_tablename . '` '
			. 'WHERE `virtuemart_order_id` = ' . $virtuemart_order_id;
		$db->setQuery($q);
		if (!($paymentTable = $db->loadObject())) {
			vmWarn(500, $q . " " . $db->getErrorMsg());
			return '';
		}
		$this->getPaymentCurrency($paymentTable);

		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE();
		$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_NAME', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE('STANDARD_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
		$html .= '</table>' . "\n";
		return $html;
	}

	public function getCosts(VirtueMartCart $cart, $method, $cart_prices)
	{
		if (preg_match('/%$/', $method->cost_percent_total)) {
			$cost_percent_total = substr($method->cost_percent_total, 0, -1);
		} else {
			$cost_percent_total = $method->cost_percent_total;
		}
		return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
	}

	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @author: Valerie Isaksen
	 *
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions($cart, $method, $cart_prices)
	{

// 		$params = new JParameter($payment->payment_params);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount      = $cart_prices['salesPrice'];
		$amount_cond = ($amount >= $method->min_amount and $amount <= $method->max_amount
			or
			($method->min_amount <= $amount and ($method->max_amount == 0)));
		if (!$amount_cond) {
			return false;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array($address)) {
			$address                          = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}

		if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
			return true;
		}

		return false;
	}

	/*
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 * @author Valérie Isaksen
	 *
	 */
	public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
	{
		return $this->onStoreInstallPluginTable($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment(VirtueMartCart $cart)
	{
		return $this->OnSelectCheck($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn)
	{
		return $this->displayListFE($cart, $selected, $htmlIn);
	}

	/*
	 * plgVmonSelectedCalculatePricePayment
	 * Calculate the price (value, tax_id) of the selected method
	 * It is called by the calculator
	 * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
	 * @author Valerie Isaksen
	 * @cart: VirtueMartCart the current cart
	 * @cart_prices: array the new cart prices
	 * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
	 *
	 *
	 */

	public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
	{
		return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
	}

	public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
	{

		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->getPaymentCurrency($method);

		$paymentCurrencyId = $method->payment_currency;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
	{
		return $this->onCheckAutomaticSelected($cart, $cart_prices);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
	{
		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}
		$this->method = $method;
		$result       = $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
		if (JRequest::getVar('option') == 'com_virtuemart' &&
			Jrequest::getVar('view') == 'orders' &&
			Jrequest::getVar('layout') == 'details') {
			if (!class_exists('CurrencyDisplay')) {
				require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'currencydisplay.php';
			}

			if (!class_exists('VirtueMartModelOrders')) {
				require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
			}

			$orderModel = VmModel::getModel('orders');
			$order      = $orderModel->getOrder($virtuemart_order_id);
			$this->getPaymentCurrency($method);
			$paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
			$totalInPaymentCurrency = $paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false);
			if (method_exists($paymentCurrency, "roundForDisplay")) {
				$totalInPaymentCurrency = number_format($paymentCurrency->roundForDisplay($totalInPaymentCurrency, $method->payment_currency), 2, '.', '');
			} else {
				$totalInPaymentCurrency = number_format(round($totalInPaymentCurrency, 2), 2, '.', '');
			}

			$redirect    = JRequest::getInt('redirect', 0);
			$printButton = $order['details']['BT']->order_status == $method->status_for_payment;
			$this->printButton(
				$payment_name,
				$totalInPaymentCurrency,
				$order,
				$redirect,
				$printButton
			);
		}
		return $result;
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	public function plgVmonShowOrderPrintPayment($order_number, $method_id)
	{
		return $this->onShowOrderPrint($order_number, $method_id);
	}

	public function plgVmDeclarePluginParamsPayment($name, $id, &$data)
	{
		return $this->declarePluginParams('payment', $name, $id, $data);
	}

	public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
	{
		return $this->setOnTablePluginParams($name, $id, $table);
	}

	public function plgVmOnUpdateOrderPayment($data, $old_status)
	{
		if (!($method = $this->getVmPluginMethod($data->virtuemart_paymentmethod_id))) {
			return null;
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return null;
		}
		$this->method = $method;
		if (!class_exists('VirtueMartModelOrders')) {
			require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}

		$orderModel = VmModel::getModel('orders');
		$order      = $orderModel->getOrder($data->virtuemart_order_id);
		$this->getPaymentCurrency($method);
		$db                     = JFactory::getDBO();
		$paymentCurrency        = CurrencyDisplay::getInstance($method->payment_currency);
		$totalInPaymentCurrency = $paymentCurrency->convertCurrencyTo($method->payment_currency, $order['details']['BT']->order_total, false);
		if (method_exists($paymentCurrency, "roundForDisplay")) {
			$totalInPaymentCurrency = number_format($paymentCurrency->roundForDisplay($totalInPaymentCurrency, $method->payment_currency), 2, '.', '');
		} else {
			$totalInPaymentCurrency = number_format(round($totalInPaymentCurrency, 2), 2, '.', '');
		}
		$db->setQuery('SELECT payment_id, status, status_extended FROM ' . $this->_tablename . ' WHERE virtuemart_order_id=' . $data->virtuemart_order_id . ' and (status="success" or status_extended = "pending_authorized") order by id desc limit 1');
		$paymentInfo = $db->loadObject();
		if (!$paymentInfo) {
			return null;
		}
		try {
			$newStatus = '';
			if ($data->order_status == $method->confirm_status && $paymentInfo->status_extended == 'pending_authorized') {
				$this->confirmPayment($paymentInfo->payment_id, $totalInPaymentCurrency);
				$newStatus = 'success';
				$msg       = 'Платеж подтверждён';
			} elseif ($data->order_status == $method->cancel_status && $paymentInfo->status_extended == 'pending_authorized') {
				$this->cancelPayment($paymentInfo->payment_id);
				$newStatus = 'failed';
				$msg       = 'Платеж отменён';
			} elseif ($data->order_status == $method->cancel_status && $paymentInfo->status == 'success') {
				$this->returnPayment($paymentInfo->payment_id, $totalInPaymentCurrency);
				$newStatus = 'failed';
				$msg       = 'Платеж возвращён';
			}
			if ($newStatus) {
				$db->setQuery("UPDATE " . $this->_tablename . " SET status='$newStatus' where payment_id='{$paymentInfo->payment_id}'");
				$db->query();
				JFactory::getApplication()->enqueueMessage($msg, 'info');
			}
		} catch (\Exception $e) {
			JFactory::getApplication()->enqueueMessage('Ошибка операции Mixplat:' . $e->getMessage(), 'error');
		}
		return null;
	}

	private function confirmPayment($paymentId, $amount)
	{
		$amount = intval($amount * 100);
		$query  = array(
			'payment_id' => $paymentId,
			'amount'     => $amount,
		);
		$query['signature'] = MixplatLib::calcActionSignature($query, $this->method->api_key);
		MixplatLib::confirmPayment($query);
	}

	private function cancelPayment($paymentId)
	{
		$query = array(
			'payment_id' => $paymentId,
		);
		$query['signature'] = MixplatLib::calcActionSignature($query, $this->method->api_key);
		MixplatLib::cancelPayment($query);
	}

	private function returnPayment($paymentId, $amount)
	{
		$amount = intval($amount * 100);
		$query  = array(
			'payment_id' => $paymentId,
			'amount'     => $amount,
		);
		$query['signature'] = MixplatLib::calcActionSignature($query, $this->method->api_key);
		MixplatLib::refundPayment($query);
	}

	public function plgVmOnPaymentNotification()
	{
		if (!class_exists('VirtueMartModelOrders')) {
			require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}
		if (JRequest::getVar('pelement') != 'mixplat') {
			return null;
		}
		$content = file_get_contents('php://input');
		$data = json_decode($content, true);
		if (!$data) {
			return null;
		}
		if (!$this->isValidRequest()) {
			return false;
		}

		$virtuemart_order_id = $data['merchant_payment_id'];
		if (!$virtuemart_order_id) {
			return false;
		}

		$payment      = $this->getDataByOrderId($virtuemart_order_id);
		$this->method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id);

		$sign = MixplatLib::calcActionSignature($data, $this->method->api_key);
		if (strcmp($sign, $data['signature']) !== 0) {
			return false;
		}
		$db = JFactory::getDBO();
		$db->setQuery("UPDATE " . $this->_tablename . " set status=" . $db->quote($data['status']) . ",status_extended=" . $db->quote($data['status_extended']) . " WHERE payment_id=" . $db->quote($data['payment_id']));
		$db->query();
		if (
			$data['status'] !== 'success'
			&& $data['status_extended'] !== 'pending_authorized') {
			return false;
		}

		$order = array();

		$order['order_status']        = $this->method->status_success;
		$order['customer_notified']   = 1;
		$order['virtuemart_order_id'] = $virtuemart_order_id;

		$order['comments'] = '';

		$modelOrder = new VirtueMartModelOrders();
		if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
			define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
		}
		try {
			$modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
		} catch (Exception $e) {};
		echo 'Ok';
		jexit();
	}

	protected function displayLogos($logo_list)
	{

		$ds = "";
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$ds = "\\";
		} else {
			$ds = "/";
		}
		$img = "";

		if (!(empty($logo_list))) {
			$url = JURI::root() . str_replace(JPATH_ROOT . $ds, '', dirname(__FILE__)) . '/';
			if (!is_array($logo_list)) {
				$logo_list = (array) $logo_list;
			}

			foreach ($logo_list as $logo) {
				if ($logo == -1) {
					continue;
				}

				$alt_text = substr($logo, 0, strpos($logo, '.'));
				$img .= '<span class="vmCartPaymentLogo"><img align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" ></span>';
			}
		}
		return $img;
	}

	public function plgVmOnPaymentResponseReceived(&$html)
	{

		if (!class_exists('VirtueMartModelOrders')) {
			require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}

		$order_number = JRequest::getVar('on', '');
		$pass         = JRequest::getVar('pass', '');

		if (!$order_number) {
			return false;
		}

		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		$payment             = $this->getDataByOrderId($virtuemart_order_id);
		if (!($method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		$msg = 'Заказ оплачен';
		JFactory::getApplication()->redirect("index.php?option=com_virtuemart&view=orders&layout=details&order_number=$order_number&order_pass=$pass", $msg);
	}

	public function plgVmOnUserPaymentCancel()
	{

		if (!class_exists('VirtueMartModelOrders')) {
			require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
		}

		$order_number = JRequest::getVar('on', '');
		$pass         = JRequest::getVar('pass', '');
		if (!$order_number) {
			return false;
		}

		$virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber($order_number);
		$payment             = $this->getDataByOrderId($virtuemart_order_id);
		if (!($method = $this->getVmPluginMethod($payment->virtuemart_paymentmethod_id))) {
			return null; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return false;
		}

		JFactory::getApplication()->redirect("index.php?option=com_virtuemart&view=orders&layout=details&order_number=$order_number&order_pass=$pass", 'Оплата не удалась, попробуйте ещё раз');

//JRequest::setVar('paymentResponse', $returnValue);
		return true;
	}

	public function isValidRequest()
	{
		if ($this->method->allowAll) {
			$mixplatIpList = explode("\n", $this->method->mixplatIpList);
			$mixplatIpList = array_map(function ($item) {return trim($item);}, $mixplatIpList);
			$ip = MixplatLib::getClientIp();
			if (!in_array($ip, $mixplatIpList)) {
				return false;
			}
		}
		return true;
	}

}
// No closing tag
