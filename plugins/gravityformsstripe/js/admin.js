/**
 * Admin Script
 */

window.GFStripeAdmin = null;

(function ($) {
    var GFStripeAdminClass = function GFStripeAdminClass() {

        var self = this;

        this.accountSettingsLocked = false;
        this.deauthActionable = false;
        this.apiMode = $('[name="_gaddon_setting_api_mode"]:checked').val();

        this.init = function() {
            this.initKeyStatus('live_publishable_key');
            this.initKeyStatus('live_secret_key');
            this.initKeyStatus('test_publishable_key');
            this.initKeyStatus('test_secret_key');
            this.bindDeauthorize();
            this.bindAPIModeChange();
            this.maybeLockAccountSettings();
            this.bindWebhookAlert();
        }

        this.validateKey = function(keyName, key){

            if(key.length == 0){
                this.setKeyStatus(keyName, "");
                return;
            }

            $('#' + keyName).val(key.trim());

            this.setKeyStatusIcon(keyName, "<img src='" + gforms_stripe_admin_strings.spinner + "'/>");

            if( keyName == "live_publishable_key" || keyName == "test_publishable_key" )
                this.validatePublishableKey(keyName, key);
            else
                this.validateSecretKey(keyName, key);

        };

        this.validateSecretKey = function(keyName, key){
            $.post(ajaxurl, {
                    action: "gf_validate_secret_key",
                    keyName: keyName,
                    key: key,
                    nonce: gforms_stripe_admin_strings.ajax_nonce
                },
                function(response) {

                    response = response.trim();

                    if(response == "valid"){
                        self.setKeyStatus(keyName, "1");
                    }
                    else if(response == "invalid"){
                        self.setKeyStatus(keyName, "0");
                    }
                    else{
                        self.setKeyStatusIcon(keyName, gforms_stripe_admin_strings.validation_error);
                    }
                }
            );
        }

        this.validatePublishableKey = function(keyName, key){
            this.setKeyStatusIcon(keyName, "<img src='" + gforms_stripe_admin_strings.spinner + "'/>");

            cc = {
                number:     "4916433572511762",
                exp_month:  "01",
                exp_year:   (new Date()).getFullYear() + 1,
                cvc:        "111",
                name:       "Test Card"
            };

            Stripe.setPublishableKey( key );
            Stripe.card.createToken( cc, function( status, response ) {

                if(status == 200){
                    self.setKeyStatus(keyName, "1");
                }
                else if( ( status == 400 || status == 402 ) && keyName == "live_publishable_key" ){
                    //Live publishable key will return a 400 or 402 status when the key is valid, but the account isn't setup to run live transactions
                    self.setKeyStatus(keyName, "1");
                }
                else{
                    self.setKeyStatus(keyName, "0");
                }
            });
        }

        this.initKeyStatus = function(keyName){
            var is_valid = $('#' + keyName + '_is_valid');
            var key = $('#' + keyName );

            if(is_valid.length > 0){
                this.setKeyStatus(keyName, is_valid.val());
            }
            else if( key.length > 0 ){
                this.validateKey(keyName, key.val());
            }


        }

        this.setKeyStatus = function(keyName, is_valid){
            $('#' + keyName + '_is_valid').val(is_valid);

            var iconMarkup = "";
            if(is_valid == "1")
                iconMarkup = "<i class=\"fa icon-check fa-check gf_valid\"></i>";
            else if(is_valid == "0")
                iconMarkup = "<i class=\"fa icon-remove fa-times gf_invalid\"></i>";

            this.setKeyStatusIcon(keyName, iconMarkup);
        }

        this.setKeyStatusIcon = function(keyName, iconMarkup){
            var icon = $('#' + keyName + "_status_icon");
            if(icon.length > 0)
                icon.remove();

            $('#' + keyName).after("<span id='" + keyName + "_status_icon'>&nbsp;&nbsp;" + iconMarkup + "</span>");
        }

        this.bindDeauthorize = function () {
            // De-Authorize from Stripe.
            $('.gform_stripe_deauth_button').on('click', function (e) {
                e.preventDefault();

                if ( self.accountSettingsLocked ) {
                    // do a reload to trigger beforeunload event.
                    window.location.reload();
                    return false;
                }

                // Get button.
                var deauthButton = $('.gform_stripe_deauth_button'),
                    deauthScope = $('.deauth_scope'),
                    disconnectMessage = gforms_stripe_admin_strings.disconnect,
                    apiMode = $(this).data('mode'),
                    feedId = $(this).data('fid');

                if (!self.deauthActionable) {
                    deauthButton.eq(0).hide();
                    if (feedId !== '') {
                        $('.connected_to_stripe_text').hide();
                    }

                    deauthScope.show('slow', function(){
                        self.deauthActionable = true;
                    });
                } else {
                    var deauthScopeVal = $('#' + apiMode + '_deauth_scope0').is(':checked') ? 'site' : 'account',
                        message = (deauthScopeVal === 'site' && feedId !== '') ? disconnectMessage['feed'] : disconnectMessage[deauthScopeVal];

                    // Confirm deletion.
                    if (!confirm(message)) {
                        return false;
                    }

                    // Set disabled state.
                    deauthButton.attr('disabled', 'disabled');

                    // De-Authorize.
                    $.ajax({
                        async: false,
                        url: ajaxurl,
                        dataType: 'json',
                        method: 'POST',
                        data: {
                            action: 'gfstripe_deauthorize',
                            scope: deauthScopeVal,
                            fid: feedId,
                            id: $(this).data('id'),
                            mode: apiMode,
                            nonce: gforms_stripe_admin_strings.ajax_nonce
                        },
                        success: function (response) {
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert(response.data.message);
                            }

                            $button.removeAttr('disabled');
                        }
                    });
                }
            });
        }

        this.bindAPIModeChange = function() {
            if ( this.apiMode === '' || typeof this.apiMode === "undefined" ) {
                this.apiMode = 'live';
                $('#api_mode0').prop('checked', true);
            }
            var hideMode = ( this.apiMode === 'live' ) ? 'test' : 'live';

            // display the Stripe Connect button in corresponding mode.
            $('#gaddon-setting-row-' + this.apiMode + '_auth_token').show();
            $('#gaddon-setting-row-' + hideMode + '_auth_token').hide();

            // Switch Stripe Connect button between live and test mode.
            $('#tab_gravityformsstripe input[name="_gaddon_setting_api_mode"]').on('click', function(e) {
                self.apiMode = $(this).val();
                hideMode = ( self.apiMode === 'live' ) ? 'test' : 'live';
                $('#gaddon-setting-row-' + hideMode + '_auth_token').hide();
                $('#gaddon-setting-row-' + self.apiMode + '_auth_token').show();
            });
        }

        this.maybeLockAccountSettings = function() {
            var apiRows = $('#gaddon-setting-row-connected_to').siblings('#gaddon-setting-row-api_mode, #gaddon-setting-row-live_auth_token, #gaddon-setting-row-test_auth_token');

            // Display the Connect To field and hide the other Stripe Account settings (only for feed settings).
			apiRows.hide();

            // When clicked on the Switch Accounts button, show other fields and disable the button itself.
            $('#gform_stripe_change_account').on('click', function() {
				if ( $(this).data('disabled') ) {
				    alert( gforms_stripe_admin_strings.switch_account_disabled_message );
				}
				else {
					apiRows.show('slow');
					self.bindAPIModeChange();
					$(this).off('click').addClass('disabled');
				}
            });

            // Track if the feed settings were changed.
            $('table.gforms_form_settings').on('change', 'input, select', function() {
                var inputName = $(this).attr('name');
                if ( inputName !== '_gaddon_setting_api_mode' && inputName !== 'deauth_scope' && inputName !== '_gaddon_setting_transactionType' ) {
                    self.accountSettingsLocked = true;
                }
            });

            // When the Update Settings button clicked, unlock the form.
            $('#gform-settings-save').on('click', function() {
                $('.error.below-h2').remove();
                self.accountSettingsLocked = false;
            });

            // Use the built-in "beforeunload" event to throw the confirmation when redirecting.
            window.addEventListener('beforeunload', function (e) {
                if ( self.accountSettingsLocked || $('.error.below-h2').length ) {
                    // Cancel the event
                    e.preventDefault();
                    // Chrome requires returnValue to be set
                    e.returnValue = '';
                }
            });
        }

        this.bindWebhookAlert = function() {
            if ( $('#gform_stripe_change_account').length && $( '#' + this.apiMode + '_signing_secret' ).val() === '' ) {
                $('#webhooks_enabled').focus();

                $([document.documentElement, document.body]).animate({
                    scrollTop: ($("#gaddon-setting-row-api_mode").offset().top + 20)
                }, 1000);
            }
        }

        this.init();
    };

    $(document).ready(function() {
        GFStripeAdmin = new GFStripeAdminClass();
    });
})(jQuery);
