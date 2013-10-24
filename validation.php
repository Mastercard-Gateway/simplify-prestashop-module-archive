<?php

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
include(dirname(__FILE__).'/simplifycommerce.php');

if (!defined('_PS_VERSION_'))
	exit;

$simplify = new SimplifyCommerce();
if ($simplify->active && isset($_POST['simplifyToken']))
	$simplify->processPayment($_POST['simplifyToken']);
else
	die('Token required, please check for any Javascript error on the payment page.');