<?php
/**
 * Plugin Settings Enable Ecommerce Tracking Instructions
 */
?>
<div id="gaecommerce-enable-ecommerce-tracking-instructions">

<a href="#" onclick="jQuery('#enable-ecommerce-tracking-visual-instructions').show();"><?php _e( 'Show Instructions', 'gravityplus-google-analytics-ecommerce-tracking' ) ?></a>

	<div id="enable-ecommerce-tracking-visual-instructions" style="display:none;">
		<img src="<?php echo $instructions_img ?>" alt="instructions" />
	</div>

	<h4><?php _e( 'Note: It can take 24-48 hours for your ecommerce tracking to start working.', 'gravityplus-google-analytics-ecommerce-tracking' ) ?></h4>

</div>