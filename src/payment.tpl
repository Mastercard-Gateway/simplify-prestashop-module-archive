<script type="text/javascript" src="{$module_dir}simplify.js"></script>
<div class="simplifyFormContainer">
	<h3><img alt="Secure Icon" class="secure-icon" src="{$module_dir}img/secure-icon.png" />{l s='Pay by Credit Card' mod='simplifycommerce'}</h3>
	<div id="simplify-ajax-loader"> 
		<span>{l s='Your payment is being processed...' mod='simplifycommerce'}</span>
		<img src="{$module_dir}img/ajax-loader.gif" alt="Loader Icon" />
	</div>
	<form action="{$module_dir}validation.php" method="POST" id="simplify-payment-form"{if isset($simplify_credit_card)} style="display: none;"{/if}>
		<div class="simplify-payment-errors">{if isset($smarty.get.simplify_error)}{$smarty.get.simplify_error|base64_decode|escape:html:'UTF-8'}{/if}</div><a name="simplify_error" style="display:none"></a>
		<label>{l s='Card Number' mod='simplifycommerce'}</label><br />
		<input type="text" size="20" autocomplete="off" class="simplify-card-number" />
		<div>
			<div class="block-left">
				<div class="clear"></div>
				<label>{l s='Expiration (MM/YYYY)' mod='simplifycommerce'}</label><br />
				<select id="month" name="month" class="simplify-card-expiry-month">
					<option value="01">{l s='January' mod='simplifycommerce'}</option>
					<option value="02">{l s='February' mod='simplifycommerce'}</option>
					<option value="03">{l s='March' mod='simplifycommerce'}</option>
					<option value="04">{l s='April' mod='simplifycommerce'}</option>
					<option value="05">{l s='May' mod='simplifycommerce'}</option>
					<option value="06">{l s='June' mod='simplifycommerce'}</option>
					<option value="07">{l s='July' mod='simplifycommerce'}</option>
					<option value="08">{l s='August' mod='simplifycommerce'}</option>
					<option value="09">{l s='September' mod='simplifycommerce'}</option>
					<option value="10">{l s='October' mod='simplifycommerce'}</option>
					<option value="11">{l s='November' mod='simplifycommerce'}</option>
					<option value="12">{l s='December' mod='simplifycommerce'}</option>
				</select>
				<span> / </span>
				<select id="year" name="year" class="simplify-card-expiry-year">
					<option value="13">2013</option>
					<option value="14">2014</option>
					<option value="15">2015</option>
					<option value="16">2016</option>
					<option value="17">2017</option>
					<option value="18">2018</option>
					<option value="19">2019</option>
					<option value="20">2020</option>
					<option value="21">2021</option>
					<option value="22">2022</option>
					<option value="23">2023</option>
					<option value="24">2024</option>
					<option value="25">2025</option>
					<option value="26">2026</option>
					<option value="27">2027</option>
					<option value="28">2028</option>
					<option value="29">2029</option>
					<option value="30">2030</option>
		        </select>
	        </div>
	        <div>
				<label>{l s='CVC' mod='simplifycommerce'}</label><br />
				<input type="text" size="4" autocomplete="off" class="simplify-card-cvc" />
				<a href="javascript:void(0)" class="simplify-card-cvc-info" style="border: none;">
					{l s='What\'s this?' mod='simplifycommerce'}
					<div class="cvc-info">
					{l s='The CVC (Card Validation Code) is a 3 or 4 digit code on the reverse side of Visa, MasterCard and Discover cards and on the front of American Express cards.' mod='simplifycommerce'}
					</div>
				</a>
			</div>
        </div>
		<br />
		<button type="submit" class="simplify-submit-button">{l s='Submit Payment' mod='simplifycommerce'}</button>
	</form>
</div>
