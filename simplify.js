/**
 * Function to handle the form submission
 */
$(document).ready(function() {

	if ($('.simplify-payment-errors').text().length > 0) {
		$('.simplify-payment-errors').show();
	}

	$('#simplify-payment-form').submit(function(event) {

		$('.simplify-payment-errors').hide();
		$('#simplify-payment-form').hide();
		$('#simplify-ajax-loader').show();
		$('.simplify-submit-button').attr('disabled', 'disabled'); /* Disable the submit button to prevent repeated clicks */

		SimplifyCommerce.generateToken({
			key: simplify_public_key,
			card: {
				number: $(".simplify-card-number").val(),
				cvc: $(".simplify-card-cvc").val(),
				expMonth: $(".simplify-card-expiry-month").val(),
				expYear: $(".simplify-card-expiry-year").val()
			}
		}, simplifyResponseHandler);

		return false; /* Prevent the form from submitting with the default action */
	});

});

/**
 * Function to handle the response from Simplify Commerce's tokenization call.
 */

function simplifyResponseHandler(data) {
	if (data.error) {
		// Show any validation errors
		if (data.error.code == "validation") {
			var fieldErrors = data.error.fieldErrors,
				fieldErrorsLength = fieldErrors.length,
				errorList = "";
			for (var i = 0; i < fieldErrorsLength; i++) {
				errorList += "<div>Field: '" + fieldErrors[i].field +
					"' is invalid - " + fieldErrors[i].message + "</div>";
			}
			// Display the errors
			$('.simplify-payment-errors').html(errorList);
			$('.simplify-payment-errors').show();
		}
		// Re-enable the submit button
		$('.simplify-submit-button').removeAttr('disabled');
		$('#simplify-payment-form').show();
		$('#simplify-ajax-loader').hide();
	} else {
		// Insert the token into the form so it gets submitted to the server
		$('#simplify-payment-form').append('<input type="hidden" name="simplifyToken" value="' + data['id'] + '" />');
		// Submit the form to the server
		$('#simplify-payment-form').get(0).submit();
	}

}