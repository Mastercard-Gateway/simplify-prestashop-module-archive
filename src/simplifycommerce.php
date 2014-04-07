<?php
/**
 * Simplify Commerce module to start accepting payments now. It's that simple.
 *
 * Redistribution and use in source and binary forms, with or without modification, are 
 * permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright notice, this list of 
 * conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this list of 
 * conditions and the following disclaimer in the documentation and/or other materials 
 * provided with the distribution.
 * Neither the name of the MasterCard International Incorporated nor the names of its 
 * contributors may be used to endorse or promote products derived from this software 
 * without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY 
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES 
 * OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT 
 * SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, 
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; 
 * OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER 
 * IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING 
 * IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF 
 * SUCH DAMAGE.
 *
 *  @author    MasterCard (support@simplify.com)
 *  @version   Release: 1.0.1
 *  @copyright 2014, MasterCard International Incorporated. All rights reserved. 
 *  @license   See licence.txt
 */

if (!defined('_PS_VERSION_'))
	exit;

/**
 * This payment module enables the processing of
 * credit card transactions through the Simplify
 * Commerce framework.
 */ 
class SimplifyCommerce extends PaymentModule
{
	public $limited_countries = array('us');
	public $limited_currencies = array('USD');
	protected $backward = false;

	/**
	 * Simplify Commerce's module constuctor
	 */
	public function __construct()
	{
		$this->name = 'simplifycommerce';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.1';
		$this->author = 'MasterCard';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('Simplify Commerce');
		$this->description = $this->l('Payments made easy - Start securely accepting credit card payments instantly.');
		$this->confirmUninstall = $this->l('Warning: Are you sure you want to uninstall this module?');

		if (_PS_VERSION_ < '1.5')
		{
			$this->backward_error = $this->l('In order to work properly in PrestaShop v1.4, the Simplify Commerce module requires the backward compatibility module at least v0.3.').'<br />'.
			$this->l('You can download this module for free here: http://addons.prestashop.com/en/modules-prestashop/6222-backwardcompatibility.html');

			if (file_exists(_PS_MODULE_DIR_.'backwardcompatibility/backward_compatibility/backward.php'))
			{
				include(_PS_MODULE_DIR_.'backwardcompatibility/backward_compatibility/backward.php');
				$this->backward = true;
			}
			else
				$this->warning = $this->backward_error;
		}
		else
			$this->backward = true;
	}

	/**
	 * Simplify Commerce's module installation
	 *
	 * @return boolean Install result
	 */
	public function install()
	{
		if (!$this->backward && _PS_VERSION_ < 1.5)
		{
			echo '<div class="error">'.Tools::safeOutput($this->backward_error).'</div>';
			return false;
		}

		/* For 1.4.3 and less compatibility */
		$update_config = array(
			'PS_OS_CHEQUE' => 1,
			'PS_OS_PAYMENT' => 2,
			'PS_OS_PREPARATION' => 3,
			'PS_OS_SHIPPING' => 4,
			'PS_OS_DELIVERED' => 5,
			'PS_OS_CANCELED' => 6,
			'PS_OS_REFUND' => 7,
			'PS_OS_ERROR' => 8,
			'PS_OS_OUTOFSTOCK' => 9,
			'PS_OS_BANKWIRE' => 10,
			'PS_OS_PAYPAL' => 11,
			'PS_OS_WS_PAYMENT' => 12
		);

		foreach ($update_config as $u => $v)
		{	
			if (!Configuration::get($u) || (int)Configuration::get($u) < 1)
			{
				if (defined('_'.$u.'_') && (int)constant('_'.$u.'_') > 0)
					Configuration::updateValue($u, constant('_'.$u.'_'));
				else
					Configuration::updateValue($u, $v);
			}

			$ret = parent::install() && 
			$this->registerHook('payment') && 
			$this->registerHook('orderConfirmation') && 
			Configuration::updateValue('SIMPLIFY_MODE', 0) && Configuration::updateValue('SIMPLIFY_SAVE_CUSTOMER_DETAILS', 1) && 
			Configuration::updateValue('SIMPLIFY_PAYMENT_ORDER_STATUS', (int)Configuration::get('PS_OS_PAYMENT')) && $this->createDatabaseTables();

			return $ret;
		}
	}

	/**
	 * Simplify Customer tables creation
	 *
	 * @return boolean Database tables installation result
	 */
	public function createDatabaseTables()
	{
		return Db::getInstance()->Execute('
			CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'simplify_customer` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
				`customer_id` varchar(32) NOT NULL, `simplify_customer_id` varchar(32) NOT NULL, `date_created` datetime NOT NULL, PRIMARY KEY (`id`), 
				KEY `customer_id` (`customer_id`), KEY `simplify_customer_id` (`simplify_customer_id`)) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 AUTO_INCREMENT=1');
	}

	/**
	 * Simplify Commerce's module uninstallation. Remove the config values and delete the tables.
	 *
	 * @return boolean Uninstall result
	 */
	public function uninstall()
	{
		return parent::uninstall() && 
		Configuration::deleteByName('SIMPLIFY_MODE') && Configuration::deleteByName('SIMPLIFY_SAVE_CUSTOMER_DETAILS') && 
		Configuration::deleteByName('SIMPLIFY_PUBLIC_KEY_TEST') && 
		Configuration::deleteByName('SIMPLIFY_PUBLIC_KEY_LIVE')&& 
		Configuration::deleteByName('SIMPLIFY_PRIVATE_KEY_TEST') && 
		Configuration::deleteByName('SIMPLIFY_PRIVATE_KEY_LIVE') && 
		Configuration::deleteByName('SIMPLIFY_PAYMENT_ORDER_STATUS') && 
		Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'simplify_customer`');
	}

	/**
	 * Display the Simplify Commerce's payment form
	 *
	 * @return string Simplify Commerce's payment form
	 */
	public function hookPayment()
	{
		// If 1.4 and no backward then leave
		if (!$this->backward)
			return;

		// If the currency is not supported, then leave
		if (!in_array($this->context->currency->iso_code, $this->limited_currencies))
			return;

		include(dirname(__FILE__).'/lib/Simplify.php');

		$api_keys = $this->getSimplifyAPIKeys();
		Simplify::$publicKey = $api_keys->publicKey;
		Simplify::$privateKey = $api_keys->privateKey;

		// If flag checked in the settings, look up customer details in the DB
		if (Configuration::get('SIMPLIFY_SAVE_CUSTOMER_DETAILS'))
		{
			$this->smarty->assign('show_save_customer_details_checkbox', true);
			$simplify_customer_id = Db::getInstance()->getValue('SELECT simplify_customer_id FROM '._DB_PREFIX_.'simplify_customer WHERE customer_id = '.(int)$this->context->cookie->id_customer);

			if ($simplify_customer_id)
			{
				// look up the customer's details
				try
				{
					$customer  = Simplify_Customer::findCustomer($simplify_customer_id);
					$this->smarty->assign('show_saved_card_details', true);
					$this->smarty->assign('customer_details', $customer);
				}
				catch (Simplify_ApiException $e)
				{
					if (class_exists('Logger'))
						Logger::addLog($this->l('Simplify Commerce - Error retrieving customer'), 1, null, 'Cart', (int)$this->context->cart->id, true);

					if ($e->getErrorCode() == 'object.not.found')
						$this->deleteCustomerFromDB(); // remove the old customer from the database, as it no longer exists in Simplify
				}
			}
		}

		// Create empty object by default
		$cardholder_details = Tools::jsonEncode(new stdClass);

		// Send the cardholder's details with the payment
		if (isset($this->context->cart->id_address_invoice))
		{
			$invoice_address = new Address((int)$this->context->cart->id_address_invoice);

			if ($invoice_address->id_state)
			{
				$state = new State((int)$invoice_address->id_state);

				if (Validate::isLoadedObject($state))
					$invoice_address->state = $state->iso_code;
			}

			$cardholder_details = Tools::jsonEncode($invoice_address);
		}

		// Set js variables to send in card tokenization
		$this->smarty->assign('simplify_public_key', Simplify::$publicKey);
		$this->smarty->assign('cardholder_details', $cardholder_details);

		// Load JS and CSS files through CCC
		$this->context->controller->addCSS($this->_path.'css/style.css');
		$this->context->controller->addJS('https://www.simplify.com/commerce/v1/simplify.js');
		$this->context->controller->addJS($this->_path.'js/simplify.js');
		$this->context->controller->addJS($this->_path.'js/simplify.form.js');

		return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
	}

	/**
	 * Display a confirmation message after an order has been placed.
	 *
	 * @param array $params Hook parameters
	 * @return string Simplify Commerce's payment confirmation screen
	 */
	public function hookOrderConfirmation($params)
	{
		if (!isset($params['objOrder']) || ($params['objOrder']->module != $this->name))
			return false;

		if ($params['objOrder'] && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid))
			$this->smarty->assign('simplify_order', array('reference' => isset($params['objOrder']->reference) ? $params['objOrder']->reference : '#'.sprintf('%06d', $params['objOrder']->id), 'valid' => $params['objOrder']->valid));

		return $this->display(__FILE__, 'views/templates/hook/order-confirmation.tpl');
	}

	/**
	 * Process a payment with Simplify Commerce.
	 * Depeding on the customer's input, we can delete/update
	 * existing customer card details and charge a payment
	 * from the generated card token.
	 */
	public function processPayment()
	{
		// If 1.4 and no backward, then leave
		if (!$this->backward)
			return;

		// Extract POST paramaters from the request
		$simplify_token_post = Tools::getValue('simplifyToken');
		$delete_customer_card_post = Tools::getValue('deleteCustomerCard');
		$save_customer_post = Tools::getValue('saveCustomer');
		$charge_customer_card = Tools::getValue('chargeCustomerCard');

		$token = !empty($simplify_token_post) ? $simplify_token_post : null;
		$should_delete_customer = !empty($delete_customer_card_post) ? $delete_customer_card_post : false;
		$should_save_customer = !empty($save_customer_post) ? $save_customer_post : false;
		$should_charge_customer_card = !empty($charge_customer_card) ? $charge_customer_card : false;

		include(dirname(__FILE__).'/lib/Simplify.php');
		$api_keys = $this->getSimplifyAPIKeys();
		Simplify::$publicKey = $api_keys->publicKey;
		Simplify::$privateKey = $api_keys->privateKey;

		// look up the customer
		$simplify_customer = Db::getInstance()->getRow('
			SELECT simplify_customer_id FROM '._DB_PREFIX_.'simplify_customer
			WHERE customer_id = '.(int)$this->context->cookie->id_customer);

		$simplify_customer_id = $this->getSimplifyCustomerID($simplify_customer['simplify_customer_id']);

		// The user has chosen to delete the credit card, so we need to delete the customer
		if (isset($simplify_customer_id) && $should_delete_customer)
		{
			try
			{
				// delete on simplify.com
				$customer  = Simplify_Customer::findCustomer($simplify_customer_id);
				$customer->deleteCustomer();
			}
			catch (Simplify_ApiException $e)
			{
				// can't find the customer on Simplify, so no need to delete
				if (class_exists('Logger'))
					Logger::addLog($this->l('Simplify Commerce - Error retrieving customer'), 1, null, 'Cart', (int)$this->context->cart->id, true);
			}	

			$this->deleteCustomerFromDB();
			$simplify_customer_id = null;
		}

		// The user has chosen to save the credit card details
		if ($should_save_customer == 'on')
		{
			// Customer exists already so update the card details from the card token
			if (isset($simplify_customer_id))
			{
				try
				{
					$customer  = Simplify_Customer::findCustomer($simplify_customer_id);
					$updates = array(
						'email' => $this->context->cookie->email,
						'name' => $this->context->cookie->customer_firstname.' '.$this->context->cookie->customer_lastname,
						'token' => $token
					);

					$customer->setAll($updates);
					$customer->updateCustomer();
				}
				catch (Simplify_ApiException $e)
				{
					if (class_exists('Logger'))
						Logger::addLog($this->l('Simplify Commerce - Error updating customer card details'), 1, null, 'Cart', (int)$this->context->cart->id, true);
				}
			}
			else
				$simplify_customer_id = $this->createNewSimplifyCustomer($token); // Create a new customer from the card token
		}

		$charge = $this->context->cart->getOrderTotal();

		try	
		{
			$amount = $charge * 100; // Cart total amount
			$description = $this->context->shop->name.$this->l(' Order Number: ').(int)$this->context->cart->id;

			if (isset($simplify_customer_id) && ($should_charge_customer_card == 'true' || $should_save_customer == 'on'))
			{
				$simplify_payment = Simplify_Payment::createPayment(array(
					'amount' => $amount,
					'customer' => $simplify_customer_id, // Customer stored in the database
					'description' => $description,
					'currency' => 'USD'
				));
			}
			else
			{
				$simplify_payment = Simplify_Payment::createPayment(array(
					'amount' => $amount,
					'token' => $token, // Token returned by Simplify Card Tokenization
					'description' => $description,
					'currency' => 'USD'
				));
			}

			$payment_status = $simplify_payment->paymentStatus;
		}
		catch(Simplify_ApiException $e)
		{
			$this->failPayment($e->getMessage());
		}

		if ($payment_status != 'APPROVED')
			$this->failPayment('The transaction was '.$payment_status);

		// Log the transaction
		$order_status = (int)Configuration::get('SIMPLIFY_PAYMENT_ORDER_STATUS');
		$message = $this->l('Simplify Commerce Transaction Details:').'\n\n'.
		$this->l('Payment ID:').' '.$simplify_payment->id.'\n'.
		$this->l('Payment Status:').' '.$simplify_payment->paymentStatus.'\n'.
		$this->l('Amount:').' '.$simplify_payment->amount * 0.01.'\n'.
		$this->l('Currency:').' '.$simplify_payment->currency.'\n'.
		$this->l('Description:').' '.$simplify_payment->description.'\n'.
		$this->l('Auth Code:').' '.$simplify_payment->authCode.'\n'.
		$this->l('Fee:').' '.$simplify_payment->fee * 0.01.'\n'.
		$this->l('Card Last 4:').' '.$simplify_payment->card->last4.'\n'.
		$this->l('Card Expiry Year:').' '.$simplify_payment->card->expYear.'\n'.
		$this->l('Card Expiry Month:').' '.$simplify_payment->card->expMonth.'\n'.
		$this->l('Card Type:').' '.$simplify_payment->card->type.'\n';

		// Create the PrestaShop order in database
		$this->validateOrder((int)$this->context->cart->id, (int)$order_status, $charge, $this->displayName, $message, array(), null, false, $this->context->customer->secure_key);

		if (version_compare(_PS_VERSION_, '1.5', '>='))
		{
			$new_order = new Order((int)$this->currentOrder);

			if (Validate::isLoadedObject($new_order))
			{
				$payment = $new_order->getOrderPaymentCollection();

				if (isset($payment[0]))
				{
					$payment[0]->transaction_id = pSQL($simplify_payment->id);
					$payment[0]->save();
				}
			}
		}

		if (_PS_VERSION_ < 1.5)
			$redirect = __PS_BASE_URI__.'order-confirmation.php?id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key;
		else
			$redirect = __PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key.'&paymentStatus='.$payment_status;

		header('Location: '.$redirect);
		exit;
	}

	/**
	 * Function to check if customer still exists in Simplify and if not to delete them from the DB.
	 *
	 * @return string Simplify customer's id.
	 */
	private function getSimplifyCustomerID($customer_id)
	{
		$simplify_customer_id = null;

		try
		{
			$customer = Simplify_Customer::findCustomer($customer_id);
			$simplify_customer_id = $customer->id;
		}
		catch (Simplify_ApiException $e)
		{
			// can't find the customer on Simplify, so no need to delete
			if (class_exists('Logger'))
				Logger::addLog($this->l('Simplify Commerce - Error retrieving customer'), 1, null, 'Cart', (int)$this->context->cart->id, true);

			if ($e->getErrorCode() == 'object.not.found')
				$this->deleteCustomerFromDB(); // remove the old customer from the database, as it no longer exists in Simplify
		}

		return $simplify_customer_id;
	}

	/**
	 * Function to create a new Simplify customer and to store its id in the database.
	 *
	 * @return string Simplify customer's id.
	 */
	private function deleteCustomerFromDB()
	{
		Db::getInstance()->Execute('DELETE FROM '._DB_PREFIX_.'simplify_customer WHERE customer_id = '.(int)$this->context->cookie->id_customer.';');
	}

	/**
	 * Function to create a new Simplify customer and to store its id in the database.
	 *
	 * @return string Simplify customer's id.
	 */
	private function createNewSimplifyCustomer($token)
	{
		try
		{
			$customer = Simplify_Customer::createCustomer(array(
				'email' => $this->context->cookie->email,
				'name' => $this->context->cookie->customer_firstname.' '.$this->context->cookie->customer_lastname,
				'token' => $token,
				'reference' => $this->context->shop->name.$this->l(' Customer ID:').' '.(int)$this->context->cookie->id_customer
			));

			$simplify_customer_id = $customer->id;

			Db::getInstance()->Execute('
				INSERT INTO '._DB_PREFIX_.'simplify_customer (id, customer_id, simplify_customer_id, date_created)
				VALUES (NULL, '.(int)$this->context->cookie->id_customer.', \''.$simplify_customer_id.'\', NOW())');
		}
		catch(Simplify_ApiException $e)
		{
			$this->failPayment($e->getMessage());
		}

		return $simplify_customer_id;
	}

	/**
	 * Function to return the user's Simplify API Keys depending on the account mode in the settings.
	 *
	 * @return object Simple object containin the Simplify public & private key values.
	 */
	private function getSimplifyAPIKeys()
	{
		$api_keys = new stdClass;
		$api_keys->publicKey = Configuration::get('SIMPLIFY_MODE') ? Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE') : Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST');
		$api_keys->privateKey = Configuration::get('SIMPLIFY_MODE') ? Configuration::get('SIMPLIFY_PRIVATE_KEY_LIVE') : Configuration::get('SIMPLIFY_PRIVATE_KEY_TEST');

		return $api_keys;
	}

	/**
	 * Function to log a failure message and redirect the user
	 * back to the payment processing screen with the error.
	 *
	 * @param string $message Error message to log and to display to the user
	 */
	private function failPayment($message)
	{
		if (class_exists('Logger'))
			Logger::addLog($this->l('Simplify Commerce - Payment transaction failed').' '.$message, 1, null, 'Cart', (int)$this->context->cart->id, true);

		$controller = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc.php' : 'order.php';
		$location = $this->context->link->getPageLink($controller).(strpos($controller, '?') !== false ? '&' : '?').'step=3&simplify_error='.base64_encode('There was a problem with your payment: '.$message).'#simplify_error';
		header('Location: '.$location);
		exit;
	}

	/**
	 * Check settings requirements to make sure the Simplify Commerce's 
	 * API keys are set.
	 *
	 * @return boolean Whether the API Keys are set or not.
	 */
	public function checkSettings()
	{
		if (Configuration::get('SIMPLIFY_MODE'))
			return Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE') != '' && Configuration::get('SIMPLIFY_PRIVATE_KEY_LIVE') != '';
		else
			return Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST') != '' && Configuration::get('SIMPLIFY_PRIVATE_KEY_TEST') != '';
	}

	/**
	 * Check technical requirements to make sure the Simplify Commerce's module will work properly
	 *
	 * @return array Requirements tests results
	 */
	public function checkRequirements()
	{
		$tests = array('result' => true);
		$tests['curl'] = array('name' => $this->l('PHP cURL extension must be enabled on your server'), 'result' => is_callable('curl_exec'));

		if (Configuration::get('SIMPLIFY_MODE'))
			$tests['ssl'] = array('name' => $this->l('SSL must be enabled on your store (before entering Live mode)'), 'result' => Configuration::get('PS_SSL_ENABLED') || (!empty($_SERVER['HTTPS']) && Tools::strtolower($_SERVER['HTTPS']) != 'off'));

		$tests['currencies'] = array('name' => $this->l('The currency USD must be enabled on your store'), 'result' => Currency::exists('GBP', 0) || Currency::exists('EUR', 0) || Currency::exists('USD', 0) || Currency::exists('CAD', 0));
		$tests['php52'] = array('name' => $this->l('Your server must run PHP 5.2 or greater'), 'result' => version_compare(PHP_VERSION, '5.2.0', '>='));
		$tests['configuration'] = array('name' => $this->l('You must set your Simplify Commerce API Keys'), 'result' => $this->checkSettings());

		if (_PS_VERSION_ < 1.5)
		{
			$tests['backward'] = array('name' => $this->l('You are using the backward compatibility module'), 'result' => $this->backward, 'resolution' => $this->backward_error);
			$tmp = Module::getInstanceByName('mobile_theme');

			if ($tmp && isset($tmp->version) && !version_compare($tmp->version, '0.3.8', '>='))
				$tests['mobile_version'] = array('name' => $this->l('You are currently using the default mobile template, the minimum version required is v0.3.8').' (v'.$tmp->version.' '.$this->l('detected').' - <a target="_blank" href="http://addons.prestashop.com/en/mobile-iphone/6165-prestashop-mobile-template.html">'.$this->l('Please Upgrade').'</a>)', 'result' => version_compare($tmp->version, '0.3.8', '>='));
		}

		foreach ($tests as $k => $test)
			if ($k != 'result' && !$test['result'])
				$tests['result'] = false;

			return $tests;
	}

	/**
	 * Display the Simplify Commerce's module settings page
	 * for the user to set their API Key pairs and choose 
	 * whether their customer's can save their card details for
	 * repeate visits.
	 *
	 * @return string Simplify settings page
	 */
	public function getContent()
	{
		$output = '';

		// Update Simplify settings
		if (Tools::isSubmit('SubmitSimplify'))
		{
			$configuration_values = array(
				'SIMPLIFY_MODE' => Tools::getValue('simplify_mode'), 
				'SIMPLIFY_SAVE_CUSTOMER_DETAILS' => Tools::getValue('simplify_save_csutomer_details'),
				'SIMPLIFY_PUBLIC_KEY_TEST' => Tools::getValue('simplify_public_key_test'),
				'SIMPLIFY_PUBLIC_KEY_LIVE' => Tools::getValue('simplify_public_key_live'),
				'SIMPLIFY_PRIVATE_KEY_TEST' => Tools::getValue('simplify_private_key_test'),
				'SIMPLIFY_PRIVATE_KEY_LIVE' => Tools::getValue('simplify_private_key_live'),
				'SIMPLIFY_PAYMENT_ORDER_STATUS' => (int)Tools::getValue('simplify_payment_status')
			);

			foreach ($configuration_values as $configuration_key => $configuration_value)
				Configuration::updateValue($configuration_key, $configuration_value);
		}

		$requirements = $this->checkRequirements();

		$output .= '
		<link href="'.$this->_path.'css/style.css" rel="stylesheet" type="text/css" media="all" />
		<link href="//fonts.googleapis.com/css?family=Lato:100,300,400,700,900" rel="stylesheet">
		<div class="simplify-module-wrapper">
		'.(Tools::isSubmit('SubmitSimplify') ? '<div class="conf confirmation">'.$this->l('Settings successfully saved').'<img src="http://www.prestashop.com/modules/'.$this->name.'.png?api_user='.urlencode($_SERVER['HTTP_HOST']).'" style="display: none;" /></div>' : '').'
		<div class="simplify-module-header">
		<a href="https://www.simplify.com/" target="_blank" class="left"><img class="logo" src="//www.simplify.com/commerce/static/images/app-logo-pos.png" alt="Simplify Commerce Logo" width="150" height="64"></a>
		<div class="header-title left">
		<h1>Start accepting payments now.</h1>
		<h2>It’s that simple.</h2>
		</div>
		<a href="https://www.simplify.com/commerce/login/merchantSignup" target="_blank" class="btn right"><span>Sign up for free</span></a>
		</div>
		<div class="section">
		<div class="clearfix">
		<div class="marketing left">
		<div class="w-container features item">
		<img class="features item icon" src="//www.simplify.com/commerce/static/images/feature_signup.jpg" alt="feature_signup.jpg">
		<h1 class="features item h1">Easy sign up</h1>
		<p>Click the "Sign up for free" button and become a Simplify merchant for free.</p>
		</div>
		</div>
		<div class="marketing left">
		<div class="w-container features item">
		<img class="features item icon" src="//www.simplify.com/commerce/static/images/feature_price.jpg" alt="feature_signup.jpg">
		<h1 class="features item h1">Simple pricing</h1>
		<p>No setup fees.<br>No monthly fees.<br>No minimum.</p>
		</div>
		</div>
		<div class="marketing left">
		<div class="w-container features item">
		<img class="features item icon" src="//www.simplify.com/commerce/static/images/feature_funding.jpg" alt="feature_signup.jpg">
		<h1 class="features item h1">Two-day funding</h1>
		<p>Deposits are made into your account in two business days for most transactions.</p>
		</div>
		</div>
		</div>
		</div>
		<div class="formContainer">
		<section class="technical-checks">
		<h2>Technical Checks</h2>
		<div class="'.($requirements['result'] ? 'conf">'.$this->l('Good news! Everything looks to be in order. Start accepting credit card payments now.') :
			'warn">'.$this->l('Unfortunately, at least one issue is preventing you from using Simplify Commerce. Please fix the issue and reload this page.')).'</div>
		<table cellspacing="0" cellpadding="0" class="simplify-technical">';
		foreach ($requirements as $k => $requirement)
			if ($k != 'result')
				$output .= '
			<tr>
			<td><img src="../img/admin/'.($requirement['result'] ? 'ok' : 'forbbiden').'.gif" alt="" /></td>
			<td>'.$requirement['name'].(!$requirement['result'] && isset($requirement['resolution']) ? '<br />'.Tools::safeOutput($requirement['resolution'], true) : '').'</td>
			</tr>';
			$output .= '
			</table>
			</section>
			<br />';

			/* If 1.4 and no backward, then leave */
			if (!$this->backward)
				return $output;

			$statuses = OrderState::getOrderStates((int)$this->context->cookie->id_lang);
			$output .= '
			<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">
			<section class="simplify-settings">
			<h2>API Key Mode</h2>
			<div class="half container">
			<div class="keyModeContainer">
			<input class="radioInput" type="radio" name="simplify_mode" value="0"'.(!Configuration::get('SIMPLIFY_MODE') ? ' checked="checked"' : '').' /><span>Test Mode</span>
			<input class="radioInput" type="radio" name="simplify_mode" value="1"'.(Configuration::get('SIMPLIFY_MODE') ? ' checked="checked"' : '').' /><span>Live Mode</span>
			</div>
			<p><div class="bold">Test Mode</div> All transactions in test mode are test payments. You can test your installation using card numbers from our <a href="https://www.simplify.com/commerce/docs/tutorial/index#testing" target="_blank">list of test card numbers</a>. You cannot process real payments in test mode, so all other card numbers will be declined.</p>
			<p><div class="bold">Live Mode</div> All transactions made in live mode are real payments and will be processed accordingly.</p>
			</div>
			<h2>Set Your API Keys</h2>
			<div class="account-mode container">
			<p>If you have not already done so, you can create an account by clicking the \'Sign up for free\' button in the top right corner.<br />Obtain both your private and public API Keys from: Account Settings -> API Keys and supply them below.</p>
			</div>	
			<div class="clearfix api-key-container">
			<div class="clearfix api-key-title">
			<div class="left"><h4 class="ng-binding">Test</h4></div>
			</div>
			<div class="api-keys">
			<div class="api-key-header clearfix">
			<div class="left api-key-key">Private Key</div>
			<div class="left api-key-key">Public Key</div>
			</div>
			<div class="api-key-box clearfix">
			<div class="left api-key-key api-key ng-binding"><input type="password" name="simplify_private_key_test" value="'.Tools::safeOutput(Configuration::get('SIMPLIFY_PRIVATE_KEY_TEST')).'" /></div>
			<div class="left api-key-key api-key ng-binding"><input type="text" name="simplify_public_key_test" value="'.Tools::safeOutput(Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST')).'" /></div>
			</div>
			</div>
			</div>

			<div class="clearfix api-key-container">
			<div class="clearfix api-key-title">
			<div class="left"><h4 class="ng-binding">Live</h4></div>
			</div>
			<div class="api-keys">
			<div class="api-key-header clearfix">
			<div class="left api-key-key">Private Key</div>
			<div class="left api-key-key">Public Key</div>
			</div>
			<div class="api-key-box clearfix">
			<div class="left api-key-key api-key ng-binding"><input type="password" name="simplify_private_key_live" value="'.Tools::safeOutput(Configuration::get('SIMPLIFY_PRIVATE_KEY_LIVE')).'" /></div>
			<div class="left api-key-key api-key ng-binding"><input type="text" name="simplify_public_key_live" value="'.Tools::safeOutput(Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE')).'" /></div>
			</div>
			</div>
			</div>
			<div class="clearfix">
			<div class="left half">
			<h2>Save Customer Details</h2>
			<div class="account-mode container">
			<p>Enable customers to save their card details securely on Simplify\'s servers for future transactions.</p>
			<div class="saveCustomerDetailsContainer">
			<input class="radioInput" type="radio" name="simplify_save_csutomer_details" value="1"'.(Configuration::get('SIMPLIFY_SAVE_CUSTOMER_DETAILS') ? ' checked="checked"' : '').' /><span>Yes</span>
			<input class="radioInput" type="radio" name="simplify_save_csutomer_details" value="0"'.(!Configuration::get('SIMPLIFY_SAVE_CUSTOMER_DETAILS') ? ' checked="checked"' : '').' /><span>No</span>
			</div>
			</div>
			</div>
			<div class="half container left">';

			$statuses_options = array(array('name' => 'simplify_payment_status', 'label' => $this->l('Sucessful Payment Order Status'), 'current_value' => Configuration::get('SIMPLIFY_PAYMENT_ORDER_STATUS')));

			foreach ($statuses_options as $status_options)
			{
				$output .= '
				<h2>'.$status_options['label'].'</h2>
				<p>Choose the status for an order once the payment has been successfully processed by Simplify.</p>
				<div>
				<select name="'.$status_options['name'].'">';
				foreach ($statuses as $status)
					$output .= '<option value="'.(int)$status['id_order_state'].'"'.($status['id_order_state'] == $status_options['current_value'] ? ' selected="selected"' : '').'>'.Tools::safeOutput($status['name']).'</option>';
				$output .= '
				</select>
				</div>';
			}

			$output .= '
			<div>
			</div>
			</div>
			</div>
			<div class="clearfix"><input type="submit" class="settings-btn btn right" name="SubmitSimplify" value="Save Settings" /></div></div>
			</section>
			</form>
			</div>
			<script type="text/javascript">
			function updateSimplifySettings()
			{
				if ($(\'input:radio[name=simplify_mode]:checked\').val() == 1)
					$(\'fieldset.simplify-cc-numbers\').hide();
				else
					$(\'fieldset.simplify-cc-numbers\').show(1000);
				
			}

			$(\'input:radio[name=simplify_mode]\').click(function() { updateSimplifySettings(); });
			$(document).ready(function() { updateSimplifySettings(); });
			</script>';

			return $output;
	}
}
?>