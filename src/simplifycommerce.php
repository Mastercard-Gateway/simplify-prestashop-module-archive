<?php


if (!defined('_PS_VERSION_'))
	exit;

/**
 * This payment module enables the processing of
 * credit card transactions through the Simplify
 * Commerce framework.
 *
 *  @author   MasterCard (support@simplify.com)
 *  @version  Release: 1.0.0
 *  @license  See licence.txt
 */ 
class SimplifyCommerce extends PaymentModule
{
	public $limited_countries = array('us');
	public $limited_currencies = array('USD', 'GBP');
	protected $backward = false;

	public function __construct()
	{
		$this->name = 'simplifycommerce';
		$this->tab = 'payments_gateways';
		$this->version = '1.0.0';
		$this->author = 'MasterCard';
		$this->need_instance = 0;

		parent::__construct();

		$this->displayName = $this->l('Simplify Commerce');
		$this->description = $this->l('Payments made easy - Start securely accepting credit card payments instantly.');
		$this->confirmUninstall = $this->l('Warning: Are you sure you want to uninstall this module?');

		if (_PS_VERSION_ < '1.5')
		{
			$this->backward_error = $this->l('In order to work properly in PrestaShop v1.4, the Simplify Commerce module requiers the backward compatibility module at least v0.3.').'<br />'.
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
		$updateConfig = array(
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
			'PS_OS_WS_PAYMENT' => 12);

		foreach ($updateConfig as $u => $v)
			if (!Configuration::get($u) || (int)Configuration::get($u) < 1)
			{
				if (defined('_'.$u.'_') && (int)constant('_'.$u.'_') > 0)
					Configuration::updateValue($u, constant('_'.$u.'_'));
				else
					Configuration::updateValue($u, $v);
			}

		$ret = parent::install() && 
		$this->registerHook('payment') && 
		$this->registerHook('header') && 
		$this->registerHook('orderConfirmation') &&
		Configuration::updateValue('SIMPLIFY_MODE', 0) && 
		Configuration::updateValue('SIMPLIFY_PAYMENT_ORDER_STATUS', (int)Configuration::get('PS_OS_PAYMENT'));

		$this->registerHook('displayMobileHeader');

		return $ret;
	}

	/**
	 * Simplify Commerce's module uninstallation (Configuration values, database tables...)
	 *
	 * @return boolean Uninstall result
	 */
	public function uninstall()
	{
		return parent::uninstall() && 
		Configuration::deleteByName('SIMPLIFY_MODE') && 
		Configuration::deleteByName('SIMPLIFY_PUBLIC_KEY_TEST') && 
		Configuration::deleteByName('SIMPLIFY_PUBLIC_KEY_LIVE')&& 
		Configuration::deleteByName('SIMPLIFY_PRIVATE_KEY_TEST') && 
		Configuration::deleteByName('SIMPLIFY_PRIVATE_KEY_LIVE') &&
		Configuration::deleteByName('SIMPLIFY_PAYMENT_ORDER_STATUS');
	}

	public function hookDisplayMobileHeader()
	{
		return $this->hookHeader();
	}

	/**
	 * Load Javascripts and CSS related to the Simplify Commerce's module
	 * during the checkout process only.
	 *
	 * @return string Simplify's JS dependencies
	 */
	public function hookHeader()
	{
		/* If 1.4 and no backward, then leave */
		if (!$this->backward)
			return;

		/* Only show if we support the currency */
		if (!in_array($this->context->currency->iso_code, $this->limited_currencies))
			return;

		/* Continue only if we are in the checkout process */
		if (Tools::getValue('controller') != 'order-opc' && (!($_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order.php' || $_SERVER['PHP_SELF'] == __PS_BASE_URI__.'order-opc.php' || Tools::getValue('controller') == 'order' || Tools::getValue('controller') == 'orderopc' || Tools::getValue('step') == 3)))
			return;

		/* Load JS and CSS files through CCC */
		$this->context->controller->addCSS($this->_path.'simplify.css');

		/* Load the Simplify.js file and set the public key variable in order to enable card tokenization */
		return '
		<script type="text/javascript" src="https://www.simplify.com/commerce/v1/simplify.js"></script>
		<script type="text/javascript">
			var simplify_public_key = \''.addslashes(Configuration::get('SIMPLIFY_MODE') ? Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE') : Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST')).'\';
		</script>';
	}

	/**
	 * Display the Simplify Commerce's payment form
	 *
	 * @param array $params Hook parameters
	 * @return string Simplify Commerce's payment form
	 */
	public function hookPayment($params)
	{
		/* If 1.4 and no backward then leave */
		if (!$this->backward)
			return;

		/* If the currency is not supported, then leave */
		if (!in_array($this->context->currency->iso_code, $this->limited_currencies))
			return ;

		return $this->display(__FILE__, 'payment.tpl');
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

		return $this->display(__FILE__, 'order-confirmation.tpl');
	}

	/**
	 * Process a payment with Simplify Commerce.
	 *
	 * @param string $token Simplify Commerce card token returned by JS call
	 */
	public function processPayment($token)
	{
		/* If 1.4 and no backward, then leave */
		if (!$this->backward)
			return;

		include(dirname(__FILE__).'/lib/Simplify.php');
		Simplify::$publicKey = Configuration::get('SIMPLIFY_MODE') ? Configuration::get('SIMPLIFY_PUBLIC_KEY_LIVE') : Configuration::get('SIMPLIFY_PUBLIC_KEY_TEST');
		Simplify::$privateKey = Configuration::get('SIMPLIFY_MODE') ? Configuration::get('SIMPLIFY_PRIVATE_KEY_LIVE') : Configuration::get('SIMPLIFY_PRIVATE_KEY_TEST');

		$charge = $this->context->cart->getOrderTotal();

		try	{
			$simplifyPayment = Simplify_Payment::createPayment(array(
		        'amount' => $charge * 100, // Cart total amount
		        'token' => $_POST['simplifyToken'], // Token returned by Simplify Card Tokenization
		        'description' => 'PrestaShop Order Number: '.(int)$this->context->cart->id,
		        'currency' => 'USD'
		     ));

      		$paymentStatus = $simplifyPayment->paymentStatus;

		} catch(Simplify_ApiException $e) {
			$this->failPayment($e->getMessage());
		}

		if($paymentStatus != 'APPROVED') {
			$this->failPayment('The transaction was '.$paymentStatus);
		}

		/* Log Transaction details */
		if (!isset($message)) {
			$order_status = (int)Configuration::get('SIMPLIFY_PAYMENT_ORDER_STATUS');
			$message = $this->l('Simplify Commerce Transaction Details:')."\n\n".
			$this->l('Payment ID:').' '.$simplifyPayment->id."\n".
			$this->l('Payment Status:').' '.$simplifyPayment->paymentStatus."\n".
			$this->l('Amount:').' '.$simplifyPayment->amount * 0.01."\n".
			$this->l('Currency:').' '.$simplifyPayment->currency."\n".
			$this->l('Description:').' '.$simplifyPayment->description."\n".
			$this->l('Auth Code:').' '.$simplifyPayment->authCode."\n".
			$this->l('Fee:').' '.$simplifyPayment->fee * 0.01."\n".
			$this->l('Card Last 4:').' '.$simplifyPayment->card->last4."\n".
			$this->l('Card Expiry Year:').' '.$simplifyPayment->card->expYear."\n".
			$this->l('Card Expiry Month:').' '.$simplifyPayment->card->expMonth."\n".
			$this->l('Card Type:').' '.$simplifyPayment->card->type."\n";
		}
		else
			$order_status = (int)Configuration::get('PS_OS_ERROR');

		/* Create the PrestaShop order in database */
		$this->validateOrder((int)$this->context->cart->id, (int)$order_status, $charge, $this->displayName, $message, array(), null, false, $this->context->customer->secure_key);

		/** @since 1.5.0 Attach the Simplify Commerce Transaction ID to this Order */
		if (version_compare(_PS_VERSION_, '1.5', '>='))
		{
			$new_order = new Order((int)$this->currentOrder);
			if (Validate::isLoadedObject($new_order))
			{
				$payment = $new_order->getOrderPaymentCollection();
				if (isset($payment[0]))
				{
					$payment[0]->transaction_id = pSQL($simplifyPayment->id);
					$payment[0]->save();
				}
			}
		}

		/* Redirect the user to the order confirmation page / history */
		if (_PS_VERSION_ < 1.5)
			$redirect = __PS_BASE_URI__.'order-confirmation.php?id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key;
		else
			$redirect = __PS_BASE_URI__.'index.php?controller=order-confirmation&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->id.'&id_order='.(int)$this->currentOrder.'&key='.$this->context->customer->secure_key.'&paymentStatus='.$paymentStatus;

		header('Location: '.$redirect);
		exit;
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
		$location=$this->context->link->getPageLink($controller).(strpos($controller, '?') !== false ? '&' : '?').'step=3&simplify_error='.base64_encode("There was a problem with your payment: ".$message).'#simplify_error';
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
			$tests['ssl'] = array('name' => $this->l('SSL must be enabled on your store (before entering Live mode)'), 'result' => Configuration::get('PS_SSL_ENABLED') || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off'));
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
	 * for the user to set their API key pairs.
	 *
	 * @return string Simplify settings page
	 */
	public function getContent()
	{
		$output = '';

		/* Update Configuration Values when settings are updated */
		if (Tools::isSubmit('SubmitSimplify'))
		{
			$configuration_values = array(
				'SIMPLIFY_MODE' => $_POST['simplify_mode'],
				'SIMPLIFY_PUBLIC_KEY_TEST' => $_POST['simplify_public_key_test'],
				'SIMPLIFY_PUBLIC_KEY_LIVE' => $_POST['simplify_public_key_live'], 
				'SIMPLIFY_PRIVATE_KEY_TEST' => $_POST['simplify_private_key_test'],
				'SIMPLIFY_PRIVATE_KEY_LIVE' => $_POST['simplify_private_key_live'], 
				'SIMPLIFY_PAYMENT_ORDER_STATUS' => (int)$_POST['simplify_payment_status']
			);

			foreach ($configuration_values as $configuration_key => $configuration_value)
				Configuration::updateValue($configuration_key, $configuration_value);
		}

		$requirements = $this->checkRequirements();

		$output .= '
		<link href="'.$this->_path.'simplify-settings.css" rel="stylesheet" type="text/css" media="all" />
		<div class="simplify-module-wrapper">
			'.(Tools::isSubmit('SubmitSimplify') ? '<div class="conf confirmation">'.$this->l('Settings successfully saved').'<img src="http://www.prestashop.com/modules/'.$this->name.'.png?api_user='.urlencode($_SERVER['HTTP_HOST']).'" style="display: none;" /></div>' : '').'
			<div class="simplify-module-header">
				<a href="https://www.simplify.com/commerce/login/signup" target="_blank"><img class="logo" src="https://www.simplify.com/commerce/static/images/app-logo.png" alt="Simplify Commerce Logo" width="100" height="43"></a>
				<a href="https://www.simplify.com/commerce/login/signup" target="_blank" class="btn right"><span>Sign up for free</span></a>
			</div>
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
				<div class="account-mode container">
					<h4>Choose the payments account mode:</h4>
					<p>This determines whether the credit card payments are processed for real or not.</p>
					<input type="radio" name="simplify_mode" value="0"'.(!Configuration::get('SIMPLIFY_MODE') ? ' checked="checked"' : '').' /><span>Test</span>
					<input type="radio" name="simplify_mode" value="1"'.(Configuration::get('SIMPLIFY_MODE') ? ' checked="checked"' : '').' /><span>Live</span>
				</div>
				<h2>Set your API Keys</h2>
				<p>If you have not done so already, you can create an account by clicking the \'Sign up for free\' button above.<br />You can then obtain your API Keys from: Account Settings -> API Keys</p>
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
                <div class="container">';

					$statuses_options = array(array('name' => 'simplify_payment_status', 'label' => $this->l('Order status in case of sucessfull payment:'), 'current_value' => Configuration::get('SIMPLIFY_PAYMENT_ORDER_STATUS')));
					foreach ($statuses_options as $status_options)
					{
						$output .= '
							<h4>'.$status_options['label'].'</h4>
							<div>
								<select name="'.$status_options['name'].'">';
									foreach ($statuses as $status)
										$output .= '<option value="'.(int)$status['id_order_state'].'"'.($status['id_order_state'] == $status_options['current_value'] ? ' selected="selected"' : '').'>'.Tools::safeOutput($status['name']).'</option>';
						$output .= '
								</select>
							</div>';
					}

					$output .= '
					<div><input type="submit" class="settings-btn btn" name="SubmitSimplify" value="Save Settings" /></div>
				</div>
			</section>
			<div class="clear"></div>
			<br />

		</div>
		</form>
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
