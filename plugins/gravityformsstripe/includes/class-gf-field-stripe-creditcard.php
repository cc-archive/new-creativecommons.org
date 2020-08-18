<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * The Stripe Card field is a credit card field used specifically by the Stripe Add-On.
 *
 * @since 2.6
 *
 * Class GF_Field_Stripe_CreditCard
 */
class GF_Field_Stripe_CreditCard extends GF_Field {

	/**
	 * Field type.
	 *
	 * @since 2.6
	 *
	 * @var string
	 */
	public $type = 'stripe_creditcard';

	/**
	 * Get field button title.
	 *
	 * @since 2.6
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Stripe Card', 'gravityformsstripe' );
	}

	/**
	 * Returns the scripts to be included for this field type in the form editor.
	 *
	 * @since  2.6
	 *
	 * @return string
	 */
	public function get_form_editor_inline_script_on_page_render() {
		$js = sprintf( "function SetDefaultValues_%s(field) {field.label = '%s';
		field.inputs = [new Input(field.id + '.1', %s), new Input(field.id + '.4', %s), new Input(field.id + '.5', %s)];
		}", $this->type, esc_html__( 'Credit Card', 'gravityformsstripe' ), json_encode( gf_apply_filters( array( 'gform_card_details', rgget( 'id' ) ), esc_html__( 'Card Details', 'gravityformsstripe' ), rgget( 'id' ) ) ), json_encode( gf_apply_filters( array( 'gform_card_type', rgget( 'id' ) ), esc_html__( 'Card Type', 'gravityformsstripe' ), rgget( 'id' ) ) ), json_encode( gf_apply_filters( array( 'gform_card_name', rgget( 'id' ) ), esc_html__( 'Cardholder Name', 'gravityformsstripe' ), rgget( 'id' ) ) ) ) . PHP_EOL;

		$js .= "gform.addFilter('gform_form_editor_can_field_be_added', function(result, type) {
            if (type === 'stripe_creditcard') {
                if (GetFieldsByType(['stripe_creditcard']).length > 0) {" .
			        sprintf( "alert(%s);", json_encode( esc_html__( 'Only one Stripe Card field can be added to the form', 'gravityformsstripe' ) ) )
			       . " result = false;
				}
            }
            
            return result;
        });";

		$js .= "jQuery(document).bind('gform_load_field_settings', function(event, field, form) {
		if (field['type']==='stripe_creditcard') {
			var imagesUrl = '" . GFCommon::get_base_url() . '/images/' . "',
			    input = field['inputs'][2],
			    isHidden = typeof input.isHidden != 'undefined' && input.isHidden ? true : false,
			    title = isHidden ? " . json_encode( esc_html__( 'Inactive', 'gravityforms' ) ) . ':' . json_encode( esc_html__( 'Active', 'gravityforms' ) ) . ",
				img = isHidden ? 'active0.png' : 'active1.png';
			jQuery('.sub_labels_setting .field_custom_inputs_ui tr:eq(0)').prepend('<td><strong>" . esc_html__( 'Show', 'gravityforms' ) . "</strong></td>');
			jQuery('.sub_labels_setting .field_custom_inputs_ui tr:eq(1)').prepend('<td></td>');
			jQuery('.sub_labels_setting .field_custom_inputs_ui tr:eq(2)').prepend('<td><img data-input_id=\'' + field['id'] + '.5\' alt=\'' + title + '\' class=\'input_active_icon cardholder_name\' src=\'' + imagesUrl + img + '\'/></td>');
			jQuery('.input_placeholders tr:eq(1)').remove();
			
			jQuery('.sub_labels_setting').on('click keypress', '.input_active_icon.cardholder_name', function(){
				var isHidden = this.src.indexOf(\"active0.png\") >= 0;
				jQuery('#input_' + field['id'] + '_1_label').toggle(!isHidden);
				jQuery('.sub_labels_setting .field_custom_inputs_ui tr:eq(2) td:eq(2) input, .sub_labels_setting .field_custom_inputs_ui tr:eq(1) td:eq(2) input').prop('disabled', isHidden);
	        });
		}
		});";

		$js .= "gform.addAction('gform_post_load_field_settings', function ([field, form]) {
			if ( field['type'] === 'stripe_creditcard' ) {	        
				// Hide #field_settings when the field has error conditions.
				// This is called right after the settings are shown. So that makes it feel like there's no settings.
				if ( jQuery('.gform_stripe_card_error').length ) {
					HideSettings( 'field_settings' );
				}
			}
		});";

		return $js;
	}

	/**
	 * Get field settings in the form editor.
	 *
	 * @since 2.6
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'force_ssl_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'rules_setting',
			'description_setting',
			'css_class_setting',
			'sub_labels_setting',
			'sub_label_placement_setting',
			'input_placeholders_setting',
		);
	}

	/**
	 * Get form editor button.
	 *
	 * @since 2.6
	 * @since 3.4 Add the Stripe Card field only when checkout method is not Checkout.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		if ( gf_stripe()->get_plugin_setting( 'checkout_method' ) !== 'stripe_checkout' ) {
			return array(
				'group' => 'pricing_fields',
				'text'  => $this->get_form_editor_field_title(),
			);
		} else {
			return array();
		}
	}

	/**
	 * Used to determine the required validation result.
	 *
	 * @since 2.6
	 *
	 * @param int $form_id The ID of the form currently being processed.
	 *
	 * @return bool
	 */
	public function is_value_submission_empty( $form_id ) {
		// check only the cardholder name.
		$cardholder_name_input = GFFormsModel::get_input( $this, $this->id . '.5' );
		$hide_cardholder_name  = rgar( $cardholder_name_input, 'isHidden' );
		$cardholder_name       = rgpost( 'input_' . $this->id . '_5' );

		if ( ! $hide_cardholder_name && empty( $cardholder_name ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get submission value.
	 *
	 * @since 2.6
	 *
	 * @param array $field_values Field values.
	 * @param bool  $get_from_post_global_var True if get from global $_POST.
	 *
	 * @return array|string
	 */
	public function get_value_submission( $field_values, $get_from_post_global_var = true ) {

		if ( $get_from_post_global_var ) {
			$value[ $this->id . '.1' ] = $this->get_input_value_submission( 'input_' . $this->id . '_1', rgar( $this->inputs[0], 'name' ), $field_values, true );
			$value[ $this->id . '.4' ] = $this->get_input_value_submission( 'input_' . $this->id . '_4', rgar( $this->inputs[1], 'name' ), $field_values, true );
			$value[ $this->id . '.5' ] = $this->get_input_value_submission( 'input_' . $this->id . '_5', rgar( $this->inputs[2], 'name' ), $field_values, true );
		} else {
			$value = $this->get_input_value_submission( 'input_' . $this->id, $this->inputName, $field_values, $get_from_post_global_var );
		}

		return $value;
	}

	/**
	 * Get field input.
	 *
	 * @since 2.6
	 *
	 * @param array      $form  The Form Object currently being processed.
	 * @param array      $value The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $value = array(), $entry = null ) {
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin = $is_entry_detail || $is_form_editor;

		$form_id  = $form['id'];
		$id       = intval( $this->id );
		$field_id = $is_entry_detail || $is_form_editor || $form_id === 0 ? "input_$id" : 'input_' . $form_id . "_$id";

		$disabled_text = $is_form_editor ? "disabled='disabled'" : '';
		$class_suffix  = $is_entry_detail ? '_admin' : '';

		$form_sub_label_placement  = rgar( $form, 'subLabelPlacement' );
		$field_sub_label_placement = $this->subLabelPlacement;
		$is_sub_label_above        = $field_sub_label_placement === 'above' || ( empty( $field_sub_label_placement ) && $form_sub_label_placement === 'above' );
		$sub_label_class_attribute = $field_sub_label_placement === 'hidden_label' ? "class='hidden_sub_label screen-reader-text'" : '';

		$card_details_input     = GFFormsModel::get_input( $this, $this->id . '.1' );
		$card_details_sub_label = rgar( $card_details_input, 'customLabel' ) !== '' ? $card_details_input['customLabel'] : esc_html__( 'Card Details', 'gravityformsstripe' );
		$card_details_sub_label = gf_apply_filters( array( 'gform_card_details', $form_id, $this->id ), $card_details_sub_label, $form_id );

		$cardholder_name_input      = GFFormsModel::get_input( $this, $this->id . '.5' );
		$hide_cardholder_name       = rgar( $cardholder_name_input, 'isHidden' );
		$cardholder_name_sub_label  = rgar( $cardholder_name_input, 'customLabel' ) !== '' ? $cardholder_name_input['customLabel'] : esc_html__( 'Cardholder Name', 'gravityformsstripe' );
		$cardholder_name_sub_label  = gf_apply_filters( array( 'gform_card_name', $form_id, $this->id ), $cardholder_name_sub_label, $form_id );
		$cardholder_name_placehoder = $this->get_input_placeholder_attribute( $cardholder_name_input );

		// Prepare the values for checking the Stripe Card field error.
		$settings_url            = add_query_arg( array(
			'page'    => 'gf_settings',
			'subview' => gf_stripe()->get_slug(),
		), admin_url( 'admin.php' ) );
		$feed_url                = add_query_arg( array(
			'page'    => 'gf_edit_forms',
			'view'    => 'settings',
			'subview' => gf_stripe()->get_slug(),
			'id'      => $form_id,
		), admin_url( 'admin.php' ) );
		$api_key                 = gf_stripe()->get_publishable_api_key();
		$stripe_checkout_enabled = gf_stripe()->is_stripe_checkout_enabled();
		$no_stripe_feed          = ! gf_stripe()->has_feed( $form_id );

		// If we are in the form editor, display a placeholder field.
		if ( $is_admin ) {
			// Display the no Publishable Key error.
			if ( empty( $api_key ) ) {
				/* translators: 1. Open div tag 2. Close div tag 3. Open link tag 4. Close link tag */
				$api_key_error = esc_html__( '%1$sPlease check your %3$sStripe API Settings%4s. Your Publishable Key is empty.%2$s' );

				return $this->get_card_error_message( $api_key_error, $settings_url );
			}

			// Display the Stripe Checkout error.
			if ( $stripe_checkout_enabled ) {
				/* translators: 1. Open div tag 2. Close div tag 3. Open link tag 4. Close link tag */
				$stripe_checkout_enabled_error = esc_html__( '%1$sThe Stripe Card field cannot work when the %3$sPayment Collection Method%4$s is set to Stripe Payment Form (Stripe Checkout).%2$s' );

				return $this->get_card_error_message( $stripe_checkout_enabled_error, $settings_url );
			}

			// Display the no Stripe feed error.
			if ( $no_stripe_feed ) {
				/* translators: 1. Open div tag 2. Close div tag 3. Open link tag 4. Close link tag */
				$no_stripe_feed_error = esc_html__( '%1$sPlease check if you have activated a %3$sStripe feed%4$s for your form.%2$s' );

				return $this->get_card_error_message( $no_stripe_feed_error, $feed_url );
			}

			$style = ( $is_admin && $hide_cardholder_name ) ? "style='display:none;'" : '';

			$cc_input = '
				<style type="text/css">
					.cc-cardnumber { width:410px; padding:7px;}
					.cc-group { position: relative; }
					.cc-group:before {
					  content: ""; position: absolute; left: 10px; top: 0; bottom: 0; width: 20px; 
					  background: url("data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB3aWR0aD0iMjJweCIgaGVpZ2h0PSIxNHB4IiB2aWV3Qm94PSIwIDAgMjIgMTQiIHZlcnNpb249IjEuMSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiB4bWxuczp4bGluaz0iaHR0cDovL3d3dy53My5vcmcvMTk5OS94bGluayI+CiAgICA8IS0tIEdlbmVyYXRvcjogU2tldGNoIDUyLjIgKDY3MTQ1KSAtIGh0dHA6Ly93d3cuYm9oZW1pYW5jb2RpbmcuY29tL3NrZXRjaCAtLT4KICAgIDx0aXRsZT5Hcm91cDwvdGl0bGU+CiAgICA8ZGVzYz5DcmVhdGVkIHdpdGggU2tldGNoLjwvZGVzYz4KICAgIDxnIGlkPSJQYWdlLTEiIHN0cm9rZT0ibm9uZSIgc3Ryb2tlLXdpZHRoPSIxIiBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPgogICAgICAgIDxnIGlkPSJHcm91cCI+CiAgICAgICAgICAgIDxyZWN0IGlkPSJSZWN0YW5nbGUiIGZpbGw9IiNEQ0RGRTYiIHg9IjAiIHk9IjAiIHdpZHRoPSIyMiIgaGVpZ2h0PSIxNCIgcng9IjIiPjwvcmVjdD4KICAgICAgICAgICAgPHJlY3QgaWQ9IlJlY3RhbmdsZSIgZmlsbD0iI0IyQjhDNiIgeD0iMyIgeT0iMTAiIHdpZHRoPSIzIiBoZWlnaHQ9IjEiPjwvcmVjdD4KICAgICAgICAgICAgPHJlY3QgaWQ9IlJlY3RhbmdsZS1Db3B5IiBmaWxsPSIjQjJCOEM2IiB4PSI3IiB5PSIxMCIgd2lkdGg9IjMiIGhlaWdodD0iMSI+PC9yZWN0PgogICAgICAgICAgICA8cmVjdCBpZD0iUmVjdGFuZ2xlLUNvcHktMiIgZmlsbD0iI0IyQjhDNiIgeD0iMTEiIHk9IjEwIiB3aWR0aD0iMyIgaGVpZ2h0PSIxIj48L3JlY3Q+CiAgICAgICAgICAgIDxyZWN0IGlkPSJSZWN0YW5nbGUtQ29weS0zIiBmaWxsPSIjQjJCOEM2IiB4PSIxNSIgeT0iMTAiIHdpZHRoPSIzIiBoZWlnaHQ9IjEiPjwvcmVjdD4KICAgICAgICAgICAgPHJlY3QgaWQ9IlJlY3RhbmdsZSIgZmlsbD0iI0ZGRkZGRiIgeD0iMyIgeT0iNCIgd2lkdGg9IjUiIGhlaWdodD0iMyI+PC9yZWN0PgogICAgICAgIDwvZz4KICAgIDwvZz4KPC9zdmc+") center / contain no-repeat;
					}
				</style>
				
				<div class="ginput_complex' . $class_suffix . ' ginput_container ginput_container_creditcard">';

			if ( $is_sub_label_above ) {
				$cc_input .= '
					<label for="' . $field_id . '_1" id="' . $field_id . '_1_label" ' . $sub_label_class_attribute . ' ' . $style . '>' . $card_details_sub_label . '</label>
					<div class="cc-group">
						<input id="' . $field_id . '_1" ' . $disabled_text . ' type="text"
						placeholder="        Card Number                                                                           MM/YY    CVC" class="cc-cardnumber">
					</div>
					<div id="' . $field_id . '_5_container" ' . $style . '>
						<label for="' . $field_id . '_5" id="' . $field_id . '_5_label" ' . $sub_label_class_attribute . '>' . $cardholder_name_sub_label . '</label>
						<input type="text" class="ginput_full" name="input_' . $id . '.5" id="' . $field_id . '_5" value="" style="padding:8px;" ' . $disabled_text . ' ' . $cardholder_name_placehoder . '>
					</div>';
			} else {
				$cc_input .= '
					<div class="cc-group">
						<input id="' . $field_id . '_1" ' . $disabled_text . ' type="text"
						placeholder="        Card Number                                                                           MM/YY    CVC" class="cc-cardnumber">
					</div>
					<label for="' . $field_id . '_1" id="' . $field_id . '_1_label" ' . $sub_label_class_attribute . ' ' . $style . '>' . $card_details_sub_label . '</label>
					<div id="' . $field_id . '_5_container" ' . $style . '>
						<input type="text" class="ginput_full" name="input_' . $id . '.5" id="' . $field_id . '_5" value="" style="padding:8px;" ' . $disabled_text . ' ' . $cardholder_name_placehoder . '>
						<label for="' . $field_id . '_5" id="' . $field_id . '_5_label" ' . $sub_label_class_attribute . '>' . $cardholder_name_sub_label . '</label>
					</div>';
			}

			$cc_input .= '</div>';

			return $cc_input;
		} else {
			$cardholder_name = '';
			if ( ! empty( $value ) ) {
				$cardholder_name = esc_attr( rgget( $this->id . '.5', $value ) );
			}

			$card_error = '';

			// Display the no Publishable Key error.
			if ( empty( $api_key ) ) {
				/* translators: 1. Open div tag 2. Close div tag */
				$api_key_error        = esc_html__( '%1$sPlease check your Stripe API Settings. Your Publishable Key is empty.%2$s' );
				$card_error           = $this->get_card_error_message( $api_key_error );
				$hide_cardholder_name = true;
			} elseif ( $stripe_checkout_enabled ) {
				// Display the Stripe Checkout error.
				/* translators: 1. Open div tag 2. Close div tag */
				$stripe_checkout_enabled_error = esc_html__( '%1$sThe Stripe Card field cannot work when the Payment Collection Method is set to Stripe Payment Form (Stripe Checkout).%2$s' );
				$card_error                    = $this->get_card_error_message( $stripe_checkout_enabled_error );
				$hide_cardholder_name          = true;
			} elseif ( $no_stripe_feed ) {
				// Display the no Stripe feed error.
				/* translators: 1. Open div tag 2. Close div tag */
				$no_stripe_feed_error = esc_html__( '%1$sPlease check if you have activated a Stripe feed for your form.%2$s' );
				$card_error           = $this->get_card_error_message( $no_stripe_feed_error );
				$hide_cardholder_name = true;
			}

			$cc_input = "<div class='ginput_complex{$class_suffix} ginput_container ginput_container_creditcard' id='{$field_id}'>";

			if ( $is_sub_label_above ) {
				$cc_input .= "
						<div class='ginput_full' id='{$field_id}_1_container'>";
				if ( ! $hide_cardholder_name ) {
					$cc_input .= "
							<label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>" . $card_details_sub_label . '</label>';
				}
				$cc_input .= "
							<div id='{$field_id}_1'></div>
							{$card_error}
						</div>";
				if ( ! $hide_cardholder_name ) {
					$cc_input .= "
						<div class='ginput_full' id='{$field_id}_5_container'>
							<label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>" . $cardholder_name_sub_label . "</label>
							<input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$cardholder_name}' {$cardholder_name_placehoder}>
						</div>";
				}
			} else {
				$cc_input .= "
						<div class='ginput_full' id='{$field_id}_1_container'>
							<div id='{$field_id}_1'></div>
							{$card_error}";
				if ( ! $hide_cardholder_name ) {
					$cc_input .= "
							<label for='{$field_id}_1' id='{$field_id}_1_label' {$sub_label_class_attribute}>" . $card_details_sub_label . '</label>';
				}
				$cc_input .= '
						</div>';
				if ( ! $hide_cardholder_name ) {
					$cc_input .= "
						<div class='ginput_full' id='{$field_id}_5_container'>
							<input type='text' name='input_{$id}.5' id='{$field_id}_5' value='{$cardholder_name}' {$cardholder_name_placehoder}>
							<label for='{$field_id}_5' id='{$field_id}_5_label' {$sub_label_class_attribute}>" . $cardholder_name_sub_label . '</label>
						</div>';
				}
			}

			$cc_input .= '</div>';

			return $cc_input;
		}
	}

	/**
	 * Returns the field markup; including field label, description, validation, and the form editor admin buttons.
	 *
	 * The {FIELD} placeholder will be replaced in GFFormDisplay::get_field_content with the markup returned by GF_Field::get_field_input().
	 *
	 * @since 2.6
	 *
	 * @param string|array $value                The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param bool         $force_frontend_label Should the frontend label be displayed in the admin even if an admin label is configured.
	 * @param array        $form                 The Form Object currently being processed.
	 *
	 * @return string
	 */
	public function get_field_content( $value, $force_frontend_label, $form ) {
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();
		$is_admin        = $is_entry_detail || $is_form_editor;

		$field_content = parent::get_field_content( $value, $force_frontend_label, $form );

		if ( ! GFCommon::is_ssl() && ! $is_admin ) {
			$field_content = "<div class='gfield_creditcard_warning_message'><span>" . esc_html__( 'This page is unsecured. Do not enter a real credit card number! Use this field only for testing purposes. ', 'gravityformsstripe' ) . '</span></div>' . $field_content;
		}

		return $field_content;
	}

	/**
	 * Get field label class.
	 *
	 * @since 2.6
	 *
	 * @return string
	 */
	public function get_field_label_class() {
		return 'gfield_label gfield_label_before_complex';
	}

	/**
	 * Get entry inputs.
	 *
	 * @since 2.6
	 *
	 * @return array|null
	 */
	public function get_entry_inputs() {
		$inputs = array();
		foreach ( $this->inputs as $input ) {
			if ( in_array( $input['id'], array( $this->id . '.1', $this->id . '.4' ), true ) ) {
				$inputs[] = $input;
			}
		}

		return $inputs;
	}

	/**
	 * Get the value in entry details.
	 *
	 * @since 2.6
	 *
	 * @param string|array $value    The field value.
	 * @param string       $currency The entry currency code.
	 * @param bool|false   $use_text When processing choice based fields should the choice text be returned instead of the value.
	 * @param string       $format   The format requested for the location the merge is being used. Possible values: html, text or url.
	 * @param string       $media    The location where the value will be displayed. Possible values: screen or email.
	 *
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {

		if ( is_array( $value ) ) {
			$card_number = trim( rgget( $this->id . '.1', $value ) );
			$card_type   = trim( rgget( $this->id . '.4', $value ) );
			$separator   = $format === 'html' ? '<br/>' : "\n";

			return empty( $card_number ) ? '' : $card_type . $separator . $card_number;
		} else {
			return '';
		}
	}

	/**
	 * Get the value when saving to an entry.
	 *
	 * @since 2.6
	 *
	 * @param string $value      The value to be saved.
	 * @param array  $form       The Form Object currently being processed.
	 * @param string $input_name The input name used when accessing the $_POST.
	 * @param int    $lead_id    The ID of the Entry currently being processed.
	 * @param array  $lead       The Entry Object currently being processed.
	 *
	 * @return array|string
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		// saving last 4 digits of credit card.
		list( $input_token, $field_id_token, $input_id ) = rgexplode( '_', $input_name, 3 );
		if ( $input_id == '1' ) {
			$value              = str_replace( ' ', '', $value );
			$card_number_length = strlen( $value );
			$value              = substr( $value, - 4, 4 );
			$value              = str_pad( $value, $card_number_length, 'X', STR_PAD_LEFT );
		} elseif ( $input_id == '4' ) {

			$value = $this->get_card_name( rgpost( "input_{$field_id_token}_4" ) );

			if ( ! $value ) {
				$card_number = rgpost( "input_{$field_id_token}_1" );
				$card_type   = GFCommon::get_card_type( $card_number );
				$value       = $card_type ? $card_type['name'] : '';
			}
		} else {
			$value = '';
		}

		return $this->sanitize_entry_value( $value, $form['id'] );
	}

	/**
	 * Returns the full name for the supplied card brand.
	 *
	 * @since 3.5
	 *
	 * @param string $slug The card brand supplied by Stripe.js.
	 *
	 * @return string
	 */
	public function get_card_name( $slug ) {
		if ( empty( $slug ) ) {
			return $slug;
		}

		$card_types = GFCommon::get_card_types();

		foreach ( $card_types as $card ) {
			if ( rgar( $card, 'slug' ) === $slug ) {
				return rgar( $card, 'name' );
			}
		}

		return $slug;
	}

	/**
	 * Display the Stripe Card error message.
	 *
	 * @since 3.5
	 *
	 * @param string $message The error message.
	 * @param string $url     The settings URL.
	 *
	 * @return string
	 */
	private function get_card_error_message( $message, $url = '' ) {
		if ( $url ) {
			return sprintf( $message, '<div class="gform_stripe_card_error">', '</div>', '<a href="' . $url . '" target="_blank">', '</a>' );
		}

		return sprintf( $message, '<div class="gfield_description validation_message">', '</div>' );
	}

}

GF_Fields::register( new GF_Field_Stripe_CreditCard() );
