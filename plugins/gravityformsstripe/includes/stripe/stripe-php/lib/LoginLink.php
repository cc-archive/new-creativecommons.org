<?php

namespace Stripe;

defined( 'ABSPATH' ) || die();

/**
 * Class LoginLink
 *
 * @property string $object
 * @property int $created
 * @property string $url
 *
 * @package Stripe
 */
class LoginLink extends ApiResource
{

    const OBJECT_NAME = "login_link";
}
