{*
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
 *}
<script>
	var simplifyPublicKey = "{$simplify_public_key}";
	var cardholderDetails = {$cardholder_details};
</script>
<div class="simplifyFormContainer">
	<h3><img alt="Secure Icon" class="secure-icon" src="{$module_dir|escape}img/secure-icon.png" />{l s='Pay by Credit Card' mod='simplifycommerce'} <span id="simplify-test-mode-msg" style="display:none;"><img alt="Secure Icon" class="warning-icon" src="{$module_dir|escape}img/warning.png" /> This is a test payment</span></h3>
	<div id="simplify-no-keys-msg" class="msg-container center" style="display:none"><img alt="Secure Icon" class="warning-icon" src="{$module_dir|escape}img/warning.png" /> {l s='Payment Form not configured correctly. Please contact support'}.</div>
	<div id="simplify-ajax-loader"> 
		<span>{l s='Your payment is being processed...' mod='simplifycommerce'}</span>
		<img src="{$module_dir|escape}img/ajax-loader.gif" alt="Loader Icon" />
	</div>
	<form action="{$module_dir|escape}validation.php" method="POST" id="simplify-payment-form">
		{if isset($show_saved_card_details)}
			<div id="old-card-container" class='card-type-container selected clearfix'>
				<div class="first card-detail left">
					<div class='card-detail-label'>&nbsp;</div>
					<input class="left" type="radio" name='cc-type' value='old' checked='checked' />
				</div>
				<div class="card-detail left">
					<div class='card-detail-label'>{l s='Card Type'}</div>
					<div class='card-detail-text'>{$customer_details->card->type|escape:'htmlall'}</div>
				</div>
				<div class="card-detail left">
					<div class='card-detail-label'>{l s='Card Number'}</div>
					<div class='card-detail-text'>xxxx - xxxx - xxxx - {$customer_details->card->last4|escape:'htmlall'}</div>
				</div>
				<div class="card-detail left">
					<div class='card-detail-label'>{l s='Expiry Date'}</div>
					<div class='card-detail-text'>
						<span class='left'>{$customer_details-> card->expMonth|escape:'htmlall'} / {$customer_details->card->expYear|escape:'htmlall'}</span>
						<div id="cc-deletion-container" class="right center">
							<div>
								<img id='trash-icon' src="{$module_dir|escape}img/trash.png" alt="trash icon" title="Delete Credit Card" />
							</div>
							<div id="cc-confirm-deletion">
								<div class='small pad-botom'>{l s='Delete Credit Card'}?</div>
								<div>
									<span id="confirm-cc-deletion">{l s='Yes'}</span>
									<span id="cancel-cc-deletion">{l s='No'}</span>
								</div>
							</div>	
						</div>

					</div>
				</div>
			</div>
			<div id="cc-deletion-msg">{l s='Your credit card has been deleted'}: <span id="cc-undo-deletion-lnk" class='underline'>Undo <img alt="Secure Icon" class="secure-icon" src="{$module_dir|escape}img/undo.png" /></span></div>
		{/if}
			<div id="new-card-container" class='card-type-container clearfix'>
		{if isset($show_saved_card_details)}
				<div class="clearfix">
					<div class="first card-detail left">
						<input class="left" type="radio" name='cc-type' value='new' />
					</div>
					<div class="card-detail left">
						<div class='card-detail-text'>{l s='New Credit Card'}</div>
					</div>
				</div>
		{/if}		
				<div id="simplify-cc-details" {if isset($show_saved_card_details)} style="display: none;"{/if} {if isset($show_saved_card_details)} class="indent"{/if}>
					<div class="simplify-payment-errors">{if isset($smarty.get.simplify_error)}{$smarty.get.simplify_error|base64_decode|escape:html:'UTF-8'}{/if}</div><a name="simplify_error" style="display:none"></a>
					<label>{l s='Card Number' mod='simplifycommerce'}</label><br />
					<input type="text" size="20" autocomplete="off" class="simplify-card-number" autofocus />
					<div>
						<div class="block-left">
							<div class="clear"></div>
							<label>{l s='Expiration (MM YYYY)' mod='simplifycommerce'}</label>
							<br />
							<div>{html_select_date display_days=false end_year='+20'|escape:'htmlall'}</div>
						</div>
						<div>
							<label>{l s='CVC' mod='simplifycommerce'}</label><br />
							<input type="text" size="4" autocomplete="off" class="simplify-card-cvc" maxlength="4"/>
							<a href="javascript:void(0)" class="simplify-card-cvc-info" style="border: none;">
								{l s='What\'s this?' mod='simplifycommerce'}
								<div class="cvc-info">
									{l s='The CVC (Card Validation Code) is a 3 or 4 digit code on the reverse side of Visa, MasterCard and Discover cards and on the front of American Express cards.' mod='simplifycommerce'}
								</div>
							</a>
						</div>
					</div>
					<br />
					{if isset($show_save_customer_details_checkbox)}
					<div>
						<input type="checkbox" name="saveCustomer">
						<span id="saveCustomerLabel" style="margin-left: 5px">{l s='Save your credit card details securely'}?</span>	
						<span id="updateCustomerLabel" style="margin-left: 5px;display:none">{l s='Update your credit card details securely'}?</span>
					</div>
					{/if}
					<div>
						<img alt="Secure Icon" class="secure-icon" src="{$module_dir|escape}img/credit-cards.png" />
					</div>
				</div>
			</div>
		<input type="submit" class="exclusive simplify-submit-button" value="{l s='Submit Payment' mod='simplifycommerce'}"/>
	</form>
</div>
