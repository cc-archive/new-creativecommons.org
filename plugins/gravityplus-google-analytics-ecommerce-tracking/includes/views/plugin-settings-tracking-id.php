<?php
/**
 * Plugin Settings Get Tracking ID Instructions
 */
?>
<div id="gaecommerce-get-tracking-id-instructions">

	<a href="#"
	   onclick="jQuery('#get-tracking-id-visual-instructions').show();"><?php _e( 'Show Instructions', 'gravityplus-google-analytics-ecommerce-tracking' ) ?></a>

	<div id="get-tracking-id-visual-instructions" style="display:none;">
		<img src="<?php echo $tracking_id_img ?>" alt="instructions"/>
	</div>

</div>