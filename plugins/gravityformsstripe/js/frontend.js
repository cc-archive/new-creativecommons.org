/**
 * Front-end Script
 */

window.GFStripe = null;

(function ($) {

	GFStripe = function (args) {

		for (var prop in args) {
			if (args.hasOwnProperty(prop))
				this[prop] = args[prop];
		}

		this.form = null;

		this.activeFeed = null;

		this.GFCCField = null;

		this.stripeResponse = null;

		this.hasPaymentIntent = false;

		this.init = function () {

			if (!this.isCreditCardOnPage()) {
				if (this.stripe_payment === 'stripe.js' || (this.stripe_payment === 'elements' && ! $('#gf_stripe_response').length)) {
					return;
				}
			}

			var GFStripeObj = this, activeFeed = null, feedActivated = false, hidePostalCode = false, apiKey = this.apiKey;

			this.form = $('#gform_' + this.formId);
			this.GFCCField = $('#input_' + this.formId + '_' + this.ccFieldId + '_1');

			gform.addAction('gform_frontend_feeds_evaluated', function (feeds, formId) {
				if ( formId !== GFStripeObj.formId ) {
					return;
				}

				activeFeed = null;
				feedActivated = false;
				hidePostalCode = false;

				for (var i = 0; i < Object.keys(feeds).length; i++) {
					if (feeds[i].addonSlug === 'gravityformsstripe' && feeds[i].isActivated) {
						feedActivated = true;

						for (var j = 0; j < Object.keys(GFStripeObj.feeds).length; j++) {
							if (GFStripeObj.feeds[j].feedId === feeds[i].feedId) {
								activeFeed = GFStripeObj.feeds[j];

								break;
							}
						}

						apiKey = activeFeed.hasOwnProperty('apiKey') ? activeFeed.apiKey : GFStripeObj.apiKey;
						GFStripeObj.activeFeed = activeFeed;

						switch (GFStripeObj.stripe_payment) {
							case 'elements':
								stripe = Stripe(apiKey);
								elements = stripe.elements();

								hidePostalCode = activeFeed.address_zip !== '';

								// If Stripe Card is already on the page (AJAX failed validation, or switch frontend feeds),
								// Destroy the card field so we can re-initiate it.
								if ( card != null && card.hasOwnProperty( '_destroyed' ) && card._destroyed === false ) {
									card.destroy();
								}

								// Clear card field errors before initiate it.
								if (GFStripeObj.GFCCField.next('.validation_message').length) {
									GFStripeObj.GFCCField.next('.validation_message').html('');
								}

								card = elements.create(
									'card',
									{
										classes: GFStripeObj.cardClasses,
										style: GFStripeObj.cardStyle,
										hidePostalCode: hidePostalCode
									}
								);

								if ( $('.gform_stripe_requires_action').length ) {
									if ( $('.ginput_container_creditcard > div').length === 2 ) {
										// Cardholder name enabled.
										$('.ginput_container_creditcard > div:last').hide();
										$('.ginput_container_creditcard > div:first').html('<p><strong>' + gforms_stripe_frontend_strings.requires_action + '</strong></p>');
									} else {
										$('.ginput_container_creditcard').html('<p><strong>' + gforms_stripe_frontend_strings.requires_action + '</strong></p>');
									}
									GFStripeObj.scaActionHandler(stripe, formId);
								} else {
									card.mount('#' + GFStripeObj.GFCCField.attr('id'));

									card.on('change', function (event) {
										GFStripeObj.displayStripeCardError(event);
									});
								}
								break;
							case 'stripe.js':
								Stripe.setPublishableKey(apiKey);
								break;
						}

						break; // allow only one active feed.
					}
				}

				if (!feedActivated) {
					if (GFStripeObj.stripe_payment === 'elements') {
						if ( elements != null && card === elements.getElement( 'card' ) ) {
							card.destroy();
						}

						if (!GFStripeObj.GFCCField.next('.validation_message').length) {
							GFStripeObj.GFCCField.after('<div class="gfield_description validation_message"></div>');
						}

						var cardErrors = GFStripeObj.GFCCField.next('.validation_message');
						cardErrors.html( gforms_stripe_frontend_strings.no_active_frontend_feed );

						wp.a11y.speak( gforms_stripe_frontend_strings.no_active_frontend_feed );
					}

					// remove Stripe fields and form status when Stripe feed deactivated
					GFStripeObj.resetStripeStatus(GFStripeObj.form, formId, GFStripeObj.isLastPage());
					apiKey = GFStripeObj.apiKey;
					GFStripeObj.activeFeed = null;
				}
			});

			switch (this.stripe_payment) {
				case 'elements':
					var stripe = null,
						elements = null,
						card = null,
						skipElementsHandler = false;

					if ( $('#gf_stripe_response').length ) {
						this.stripeResponse = JSON.parse($('#gf_stripe_response').val());

						if ( this.stripeResponse.hasOwnProperty('client_secret') ) {
							this.hasPaymentIntent = true;
						}
					}
					break;
			}

			// bind Stripe functionality to submit event
			$('#gform_' + this.formId).on('submit', function (event) {
				// by checking if GFCCField is hidden, we can continue to the next page in a multi-page form
				if (!feedActivated || $(this).data('gfstripesubmitting') || $('#gform_save_' + GFStripeObj.formId).val() == 1 || (!GFStripeObj.isLastPage() && 'elements' !== GFStripeObj.stripe_payment) || gformIsHidden(GFStripeObj.GFCCField) || GFStripeObj.maybeHitRateLimits() || GFStripeObj.invisibleCaptchaPending()) {
					return;
				} else {
					event.preventDefault();
					$(this).data('gfstripesubmitting', true);
					GFStripeObj.maybeAddSpinner();
				}

				switch (GFStripeObj.stripe_payment) {
					case 'elements':
						GFStripeObj.form = $(this);

						if ( activeFeed.paymentAmount === 'form_total' ) {
							// Set priority to 51 so it will be triggered after the coupons add-on
							gform.addFilter('gform_product_total', function (total, formId) {
								window['gform_stripe_amount_' + formId] = total;
								return total;
							}, 51);

							gformCalculateTotalPrice(GFStripeObj.formId);
						}

						GFStripeObj.updatePaymentAmount();

						// don't create card token if clicking on the Previous button.
						var sourcePage = parseInt($('#gform_source_page_number_' + GFStripeObj.formId).val(), 10),
						    targetPage = parseInt($('#gform_target_page_number_' + GFStripeObj.formId).val(), 10);
						if ((sourcePage > targetPage && targetPage !== 0) || window['gform_stripe_amount_' + GFStripeObj.formId] === 0) {
							skipElementsHandler = true;
						}

						if ((GFStripeObj.isLastPage() && !GFStripeObj.isCreditCardOnPage()) || gformIsHidden(GFStripeObj.GFCCField) || skipElementsHandler) {
							$(this).submit();
							return;
						}

						if ( activeFeed.type === 'product' ) {
							// Create a new payment method when every time the Stripe Elements is resubmitted.
							GFStripeObj.createPaymentMethod(stripe, card);
						} else {
							GFStripeObj.createToken(stripe, card);
						}
						break;
					case 'stripe.js':
						var form = $(this),
							ccInputPrefix = 'input_' + GFStripeObj.formId + '_' + GFStripeObj.ccFieldId + '_',
							cc = {
								number: form.find('#' + ccInputPrefix + '1').val(),
								exp_month: form.find('#' + ccInputPrefix + '2_month').val(),
								exp_year: form.find('#' + ccInputPrefix + '2_year').val(),
								cvc: form.find('#' + ccInputPrefix + '3').val(),
								name: form.find('#' + ccInputPrefix + '5').val()
							};


						GFStripeObj.form = form;

						Stripe.card.createToken(cc, function (status, response) {
							GFStripeObj.responseHandler(status, response);
						});
						break;
				}

			});

		};

		this.getBillingAddressMergeTag = function (field) {
			if (field === '') {
				return '';
			} else {
				return '{:' + field + ':value}';
			}
		};

		this.responseHandler = function (status, response) {

			var form = this.form,
				ccInputPrefix = 'input_' + this.formId + '_' + this.ccFieldId + '_',
				ccInputSuffixes = ['1', '2_month', '2_year', '3', '5'];

			// remove "name" attribute from credit card inputs
			for (var i = 0; i < ccInputSuffixes.length; i++) {

				var input = form.find('#' + ccInputPrefix + ccInputSuffixes[i]);

				if (ccInputSuffixes[i] == '1') {

					var ccNumber = $.trim(input.val()),
						cardType = gformFindCardType(ccNumber);

					if (typeof this.cardLabels[cardType] != 'undefined')
						cardType = this.cardLabels[cardType];

					form.append($('<input type="hidden" name="stripe_credit_card_last_four" />').val(ccNumber.slice(-4)));
					form.append($('<input type="hidden" name="stripe_credit_card_type" />').val(cardType));

				}

				// name attribute is now removed from markup in GFStripe::add_stripe_inputs()
				//input.attr( 'name', null );

			}

			// append stripe.js response
			form.append($('<input type="hidden" name="stripe_response" />').val($.toJSON(response)));

			// submit the form
			form.submit();

		};

		this.elementsResponseHandler = function (response) {

			var form = this.form,
				GFStripeObj = this,
				activeFeed = this.activeFeed,
			    currency = gform.applyFilters( 'gform_stripe_currency', this.currency, this.formId ),
				amount = (0 === gf_global.gf_currency_config.decimals) ? window['gform_stripe_amount_' + this.formId] : gformRoundPrice( window['gform_stripe_amount_' + this.formId] * 100 );

			if (response.error) {
				// display error below the card field.
				this.displayStripeCardError(response);
				// when Stripe response contains errors, stay on page
				// but remove some elements so the form can be submitted again
				// also remove last_4 and card type if that already exists (this happens when people navigate back to previous page and submit an empty CC field)
				this.resetStripeStatus(form, this.formId, this.isLastPage());

				return;
			}

			if (!this.hasPaymentIntent) {
				// append stripe.js response
				if (!$('#gf_stripe_response').length) {
					form.append($('<input type="hidden" name="stripe_response" id="gf_stripe_response" />').val($.toJSON(response)));
				} else {
					$('#gf_stripe_response').val($.toJSON(response));
				}

				if (activeFeed.type === 'product') {
					//set last 4
					form.append($('<input type="hidden" name="stripe_credit_card_last_four" id="gf_stripe_credit_card_last_four" />').val(response.paymentMethod.card.last4));

					// set card type
					form.append($('<input type="hidden" name="stripe_credit_card_type" id="stripe_credit_card_type" />').val(response.paymentMethod.card.brand));
					// Create server side payment intent.
					$.ajax({
						async: false,
						url: gforms_stripe_frontend_strings.ajaxurl,
						dataType: 'json',
						method: 'POST',
						data: {
							action: "gfstripe_create_payment_intent",
							nonce: gforms_stripe_frontend_strings.create_payment_intent_nonce,
							payment_method: response.paymentMethod,
							currency: currency,
							amount: amount,
							feed_id: activeFeed.feedId
						},
						success: function (response) {
							if (response.success) {
								// populate the stripe_response field again.
								if (!$('#gf_stripe_response').length) {
									form.append($('<input type="hidden" name="stripe_response" id="gf_stripe_response" />').val($.toJSON(response.data)));
								} else {
									$('#gf_stripe_response').val($.toJSON(response.data));
								}
								// submit the form
								form.submit();
							} else {
								response.error = response.data;
								delete response.data;
								GFStripeObj.displayStripeCardError(response);
								GFStripeObj.resetStripeStatus(form, GFStripeObj.formId, GFStripeObj.isLastPage());
							}
						}
					});
				} else {
					form.append($('<input type="hidden" name="stripe_credit_card_last_four" id="gf_stripe_credit_card_last_four" />').val(response.token.card.last4));
					form.append($('<input type="hidden" name="stripe_credit_card_type" id="stripe_credit_card_type" />').val(response.token.card.brand));
					form.submit();
				}
			} else {
				if (activeFeed.type === 'product') {
					if (response.hasOwnProperty('paymentMethod')) {
						$('#gf_stripe_credit_card_last_four').val(response.paymentMethod.card.last4);
						$('#stripe_credit_card_type').val(response.paymentMethod.card.brand);

						$.ajax({
							async: false,
							url: gforms_stripe_frontend_strings.ajaxurl,
							dataType: 'json',
							method: 'POST',
							data: {
								action: "gfstripe_update_payment_intent",
								nonce: gforms_stripe_frontend_strings.create_payment_intent_nonce,
								payment_intent: response.id,
								payment_method: response.paymentMethod,
								currency: currency,
								amount: amount,
								feed_id: activeFeed.feedId
							},
							success: function (response) {
								if (response.success) {
									$('#gf_stripe_response').val($.toJSON(response.data));
									form.submit();
								} else {
									response.error = response.data;
									delete response.data;
									GFStripeObj.displayStripeCardError(response);
									GFStripeObj.resetStripeStatus(form, GFStripeObj.formId, GFStripeObj.isLastPage());
								}
							}
						});
					} else if (response.hasOwnProperty('amount')) {
						form.submit();
					}
				} else {
					var currentResponse = JSON.parse($('#gf_stripe_response').val());
					currentResponse.updatedToken = response.token.id;

					$('#gf_stripe_response').val($.toJSON(currentResponse));

					form.append($('<input type="hidden" name="stripe_credit_card_last_four" id="gf_stripe_credit_card_last_four" />').val(response.token.card.last4));
					form.append($('<input type="hidden" name="stripe_credit_card_type" id="stripe_credit_card_type" />').val(response.token.card.brand));
					form.submit();
				}
			}
		};

		this.scaActionHandler = function (stripe, formId) {
			if ( ! $('#gform_' + formId).data('gfstripescaauth') ) {
				$('#gform_' + formId).data('gfstripescaauth', true);

				var GFStripeObj = this, response = JSON.parse($('#gf_stripe_response').val());
				if (this.activeFeed.type === 'product') {
					// Prevent the 3D secure auth from appearing twice, so we need to check if the intent status first.
					stripe.retrievePaymentIntent(
						response.client_secret
					).then(function(result) {
						if ( result.paymentIntent.status === 'requires_action' ) {
							stripe.handleCardAction(
								response.client_secret
							).then(function(result) {
								var currentResponse = JSON.parse($('#gf_stripe_response').val());
								currentResponse.scaSuccess = true;

								$('#gf_stripe_response').val($.toJSON(currentResponse));

								GFStripeObj.maybeAddSpinner();
								$('#gform_' + formId).data('gfstripescaauth', false);
								$('#gform_' + formId).data('gfstripesubmitting', true).submit();
							});
						}
					});
				} else {
					stripe.retrievePaymentIntent(
						response.client_secret
					).then(function(result) {
						if ( result.paymentIntent.status === 'requires_action' ) {
							stripe.handleCardPayment(
								response.client_secret
							).then(function(result) {
								GFStripeObj.maybeAddSpinner();
								$('#gform_' + formId).data('gfstripescaauth', false);
								$('#gform_' + formId).data('gfstripesubmitting', true).submit();
							});
						}
					});
				}
			}
		};

		this.isLastPage = function () {

			var targetPageInput = $('#gform_target_page_number_' + this.formId);
			if (targetPageInput.length > 0)
				return targetPageInput.val() == 0;

			return true;
		};

		this.isCreditCardOnPage = function () {

			var currentPage = this.getCurrentPageNumber();

			// if current page is false or no credit card page number, assume this is not a multi-page form
			if (!this.ccPage || !currentPage)
				return true;

			return this.ccPage == currentPage;
		};

		this.getCurrentPageNumber = function () {
			var currentPageInput = $('#gform_source_page_number_' + this.formId);
			return currentPageInput.length > 0 ? currentPageInput.val() : false;
		};

		this.maybeAddSpinner = function () {
			if (this.isAjax)
				return;

			if (typeof gformAddSpinner === 'function') {
				gformAddSpinner(this.formId);
			} else {
				// Can be removed after min Gravity Forms version passes 2.1.3.2.
				var formId = this.formId;

				if (jQuery('#gform_ajax_spinner_' + formId).length == 0) {
					var spinnerUrl = gform.applyFilters('gform_spinner_url', gf_global.spinnerUrl, formId),
						$spinnerTarget = gform.applyFilters('gform_spinner_target_elem', jQuery('#gform_submit_button_' + formId + ', #gform_wrapper_' + formId + ' .gform_next_button, #gform_send_resume_link_button_' + formId), formId);
					$spinnerTarget.after('<img id="gform_ajax_spinner_' + formId + '"  class="gform_ajax_spinner" src="' + spinnerUrl + '" alt="" />');
				}
			}

		};

		this.resetStripeStatus = function(form, formId, isLastPage) {
			$('#gf_stripe_response, #gf_stripe_credit_card_last_four, #stripe_credit_card_type').remove();
			form.data('gfstripesubmitting', false);
            $('#gform_ajax_spinner_' + formId).remove();

			// must do this or the form cannot be submitted again
			if (isLastPage) {
				window["gf_submitting_" + formId] = false;
			}
		};

		this.displayStripeCardError = function (event) {
			if (!this.GFCCField.next('.validation_message').length) {
				this.GFCCField.after('<div class="gfield_description validation_message"></div>');
			}

			var cardErrors = this.GFCCField.next('.validation_message');

			if (event.error) {
				cardErrors.html(event.error.message);

				wp.a11y.speak( event.error.message, 'assertive' );
				// Hide spinner.
				if ( $('#gform_ajax_spinner_' + this.formId).length > 0 ) {
					$('#gform_ajax_spinner_' + this.formId).remove();
				}
			} else {
				cardErrors.html('');
			}
		};

		this.updatePaymentAmount = function () {
			var formId = this.formId, activeFeed = this.activeFeed;

			if (activeFeed.paymentAmount !== 'form_total') {
				var price = GFMergeTag.getMergeTagValue(formId, activeFeed.paymentAmount, ':price'),
					qty = GFMergeTag.getMergeTagValue(formId, activeFeed.paymentAmount, ':qty');

				if (typeof price === 'string') {
					price = GFMergeTag.getMergeTagValue(formId, activeFeed.paymentAmount + '.2', ':price');
					qty = GFMergeTag.getMergeTagValue(formId, activeFeed.paymentAmount + '.3', ':qty');
				}

				window['gform_stripe_amount_' + formId] = price * qty;
			}

			if (activeFeed.hasOwnProperty('setupFee')) {
				price = GFMergeTag.getMergeTagValue(formId, activeFeed.setupFee, ':price');
				qty = GFMergeTag.getMergeTagValue(formId, activeFeed.setupFee, ':qty');

				if (typeof price === 'string') {
					price = GFMergeTag.getMergeTagValue(formId, activeFeed.setupFee + '.2', ':price');
					qty = GFMergeTag.getMergeTagValue(formId, activeFeed.setupFee + '.3', ':qty');
				}

				window['gform_stripe_amount_' + formId] += price * qty;
			}
		};

		this.createToken = function (stripe, card) {
			var GFStripeObj = this, activeFeed = this.activeFeed;
				cardholderName = $( '#input_' + this.formId + '_' + this.ccFieldId + '_5' ).val(),
				tokenData = {
					name: cardholderName,
					address_line1: GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_line1)),
					address_line2: GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_line2)),
					address_city: GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_city)),
					address_state: GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_state)),
					address_zip: GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_zip)),
					address_country: GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_country)),
					currency: gform.applyFilters( 'gform_stripe_currency', this.currency, this.formId )
				};
			stripe.createToken(card, tokenData).then(function (response) {
				GFStripeObj.elementsResponseHandler(response);
			});
		};

		this.createPaymentMethod = function (stripe, card, country) {
			var GFStripeObj = this, activeFeed = this.activeFeed, countryFieldValue = '';

			if ( activeFeed.address_country !== '' ) {
				countryFieldValue = GFMergeTag.replaceMergeTags(GFStripeObj.formId, GFStripeObj.getBillingAddressMergeTag(activeFeed.address_country));
			}

			if (countryFieldValue !== '' && ( typeof country === 'undefined' || country === '' )) {
                $.ajax({
                    async: false,
                    url: gforms_stripe_frontend_strings.ajaxurl,
                    dataType: 'json',
                    method: 'POST',
                    data: {
                        action: "gfstripe_get_country_code",
                        nonce: gforms_stripe_frontend_strings.create_payment_intent_nonce,
                        country: countryFieldValue,
                        feed_id: activeFeed.feedId
                    },
                    success: function (response) {
                        if (response.success) {
                            GFStripeObj.createPaymentMethod(stripe, card, response.data.code);
                        }
                    }
                });
            } else {
                var cardholderName = $('#input_' + this.formId + '_' + this.ccFieldId + '_5').val(),
					line1 = GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_line1)),
					line2 = GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_line2)),
					city = GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_city)),
					state = GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_state)),
					postal_code = GFMergeTag.replaceMergeTags(this.formId, this.getBillingAddressMergeTag(activeFeed.address_zip)),
                    data = { billing_details: { name: null, address: {} } };

                if (cardholderName !== '') {
                	data.billing_details.name = cardholderName;
				}
				if (line1 !== '') {
					data.billing_details.address.line1 = line1;
				}
				if (line2 !== '') {
					data.billing_details.address.line2 = line2;
				}
				if (city !== '') {
					data.billing_details.address.city = city;
				}
				if (state !== '') {
					data.billing_details.address.state = state;
				}
				if (postal_code !== '') {
					data.billing_details.address.postal_code = postal_code;
				}
				if (country !== '') {
					data.billing_details.address.country = country;
				}

				if (data.billing_details.name === null) {
					delete data.billing_details.name;
				}
				if (data.billing_details.address === {}) {
					delete data.billing_details.address;
				}

				stripe.createPaymentMethod('card', card, data).then(function (response) {
					if (GFStripeObj.stripeResponse !== null) {
						response.id = GFStripeObj.stripeResponse.id;
						response.client_secret = GFStripeObj.stripeResponse.client_secret;
					}

					GFStripeObj.elementsResponseHandler(response);
				});
            }
		};

		this.maybeHitRateLimits = function() {
			if (this.hasOwnProperty('cardErrorCount')) {
				if (this.cardErrorCount >= 5) {
					return true;
				}
			}

			return false;
		};

		this.invisibleCaptchaPending = function () {
			var form = this.form,
				reCaptcha = form.find('.ginput_recaptcha');

			if (!reCaptcha.length || reCaptcha.data('size') !== 'invisible') {
				return false;
			}

			var reCaptchaResponse = reCaptcha.find('.g-recaptcha-response');

			return !(reCaptchaResponse.length && reCaptchaResponse.val());
		}

		this.init();

	}

})(jQuery);