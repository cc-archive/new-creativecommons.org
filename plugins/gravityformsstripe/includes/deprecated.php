<?php

if ( ! class_exists( 'Stripe_InvoiceItem' ) ) {
	/**
	 * Class Stripe_InvoiceItem
	 *
	 * Used in the example for the gform_stripe_customer_after_create hook.
	 *
	 * @deprecated
	 */
	class Stripe_InvoiceItem extends \Stripe\InvoiceItem {}
}

if ( ! class_exists( 'Stripe_Charge' ) ) {
	/**
	 * Class Stripe_Charge
	 *
	 * @deprecated
	 */
	class Stripe_Charge extends \Stripe\Charge {}
}
