// Custom login-registration AJAX 

( function( $, plugin ) {

	$(function() {
		"use strict";
		/* Helper function to process errors */
		var createResponse = function (response, form) {
			if (response.response == 'error') {
				if (response.field instanceof Array) {
					$(response.field).each(function (index, value) {
						$(form).find("#" + value).after('<small class="error">' + response.message + '</small>');
						$(form).find("#" + response.field).parent().addClass('error');
					});
				}
				else {
					$(form).find("#" + response.field).after('<small class="error">' + response.message + '</small>');
					$(form).find("#" + response.field).parent().addClass('error');
				}
				if (response.field == false) {
					$(form).append(response.message);
				}
			}
			if (response.response == 'success') {
				$(form).replaceWith('<p class="confirm">' + response.message + '</p>');
			}
		}

		$('#popup-registration .popup-closer').on('click', function () {
			var data = {
				action: plugin.action_loggedin
			};

			$.post(plugin.url, data, function (response) {
				// Check to make sure if user logged in:
				// console.log(response); // should be 0 or 1
				if (response == true) {
					document.location.reload(true);
				}
			});
		});

		/* Helper Function Check for User Logged in */
		// Update menu upon clicking out of the popup.
		$('.popup').on('click', function (event) {
			var data = {
				action: plugin.action_loggedin
			};

			$.post(plugin.url, data, function (response) {
				// Check to make sure if user logged in:
				// console.log(response); // should be 0 or 1
				if (response == true) {
					var userNav = plugin.logged_in_content;
					$('nav.user-nav').html(userNav);
				}
			});
		});

		/* Login Form Ajax Submit */
		$('#loginform').on('submit', function (event) {
			event.preventDefault();
			var form = this,
				formdata = $(form).serializeArray();

			var data = {
				action: plugin.action_user_login,
				formdata: formdata,
				nonce: plugin.nonce
			};

			$.post(plugin.url, data, function (response) {
				// console.log(response);
				if (response.response == 'error') {
					$(form).addClass('error').append('<small class="error">' + response.message + '</small>');
				}
				if (response.response == 'success') {
					$(form).replaceWith('<p class="confirm">' + response.message + '</p>');
					setTimeout(document.location.reload(true), 3000);
				}
			});
		});

		/* Form to update the password -- validate and submit all the fields */
		$('#change_password_form').on('submit', function (event) {
			event.preventDefault();
			var form = this,
				formdata = $(form).serializeArray();
			// console.log(formdata);

			var data = {
				action: plugin.action_check_validate_pswd,
				formdata: formdata,
				nonce: plugin.nonce
			};

			$.post(plugin.url, data, function (response) {
				console.log(response);
				if (response.success == false) {
					if (!response.data.field) {
						$(form).replaceWith('<p class="confirm">' + response.data.message + '</p>');
					}
					else {
						$("#" + response.data.field).after('<p class="confirm">' + response.data.message + '</p>');
					}
				}
				else {
					$(form).replaceWith('<p class="confirm">' + response.data.message + '</p>');
					setTimeout(document.location.replace('/'), 4000);
				}
			}).fail(function (jqXHR, textStatus, errorThrown) {
				console.log('AJAX failed', jqXHR.getAllResponseHeaders(), textStatus, errorThrown);
			});
		});

		/* Send an email in order to reset password */
		$('#usr_account_reset_form').on('submit', function (event) {
			event.preventDefault();
			var form = this,
				formdata = $(form).serializeArray();
			// console.log(formdata);
			var data = {
				action: plugin.action_send_password_reset,
				formdata: formdata,
				nonce: plugin.nonce
			};
			// make the ajax call and create errors
			$.post(plugin.url, data, function (response) {
				// console.log('response:', response);
				if (response && response.success === true) {
					$(form).replaceWith('<p>' + response.data.message + '</p>');
					window.activatePopups();
				}
				else {
					$(form).replaceWith('<p class="error">' + response.data.message + '</p>');
					window.activatePopups();
				}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				console.log('AJAX failed', jqXHR.getAllResponseHeaders(), textStatus, errorThrown);
			});
		});

		/* Registration form */
		$('#user_account_custom_registration_form').on('submit', function (event) {
			var form = this;
			$(form).find('small.error').remove();
			$(form).find('.error').removeClass('error');
			event.preventDefault();

			// get all my serialized data
			var formdata = {
				account_user_login: $('#account_user_login').val(),
				account_user_email: $('#account_user_email').val(),
				account_user_password: $('#account_user_password').val(),
				account_user_password_again: $('#account_user_password_again').val()
			};
			// Make a connection with our PHP
			$.ajax({
				type: "POST",
				url: plugin.url,
				dataType: "json",
				data: {
					action: plugin.action,
					security: plugin.nonce,
					formdata: formdata
				}
			})
			.done(function (response) {
					console.log(response);
					createResponse(response, form);
					if ( response.response === 'success' ) {
						$("#popup-lead-qualifier").addClass("active");
					}
					else {
						createResponse(response, form);
					}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				$(form).html('').replaceWith('<p class="confirm">' + 'Something went wrong! Please reload the page and try again. If this problem persists please contact support.' + '</p>');
			});
		});

	});

} )( jQuery, useraccountajaxObject || {} );

