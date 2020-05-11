jQuery(document).ready( function($) {


	// Identify the EN page Types that exist which their own fieldgroups
	var pageTypes = [
		'nd'
	];


	/**
	 * First, hide all the optional page type groups
	 */
	$.each( pageTypes, function( index, val ) {
		$('#gaddon-setting-row-' + val + 'Fields').hide();
	})


	/**
	 * Use magic 'req' string to convert fields to required, both for user display and JS validation
	 */
	$('tr[id^="gaddon-setting-row-"] label[for$="_req"]').each( function() {

		// Display 'required' asterisk.
		var newHtml = $(this).html().replace(' req', ' <span class="required">*</span>');
		$(this).html( newHtml );

		// Set a class on the input so we know to check it on form submit. Traversal is fun!
		$(this).parent('td').next('td').find('select').addClass('gfen-required');
	});


	/**
	 * When the select for 'pageId' is changed, show/hide field groups as appropriate
	 */
	$('#tab_gravityforms-en #pageId').on('change', function() {

		var pageVal = $(this).val();
			pageProps = pageVal.split(/\s*\-\s*/g),
			pageType = pageProps[0];

		if ( 'nd' == pageType ) {
			$('#gaddon-setting-row-' + pageType + 'Fields:hidden').show('fast');
		} else {
			$('#gaddon-setting-row-ndFields:visible').hide('fast');
		}

		if ( 'None' === pageVal ) {
			$('#gaddon-setting-row-pageFields:visible').hide('fast');
		} else {
			$('#gaddon-setting-row-pageFields:hidden').show('fast');
		}

	}).trigger('change'); // Also trigger change so that saved values load properly


  /**
   * Validate fields via JS
   */
  $('#tab_gravityforms-en form#gform-settings').on( 'submit', function() {

		var passedValidation = true;

		$( '.gfen-required:visible', this ).each( function() {
			if ( $(this).val() == '' ) {
				var thisID = $(this).attr('id');
				var $thisLabel = $('label[for="'+ thisID +'"]');
				alert("You must map a field for '" + $thisLabel.text().replace(' *', '') + "'" );
				$(this).focus();
				passedValidation = false;
				return false;
			}
		});

		return passedValidation;
  });

});