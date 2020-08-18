<?php

namespace Stripe\Error;

defined( 'ABSPATH' ) || die();

class InvalidRequest extends Base
{
    public function __construct(
        $message,
        $stripeParam,
        $httpStatus = null,
        $httpBody = null,
        $jsonBody = null,
        $httpHeaders = null
    ) {
        parent::__construct($message, $httpStatus, $httpBody, $jsonBody, $httpHeaders);
        $this->stripeParam = $stripeParam;
    }

    public function getStripeParam()
    {
        return $this->stripeParam;
    }
}
