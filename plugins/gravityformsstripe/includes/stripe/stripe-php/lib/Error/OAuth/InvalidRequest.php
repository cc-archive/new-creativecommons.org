<?php

namespace Stripe\Error\OAuth;

defined( 'ABSPATH' ) || die();

/**
 * InvalidRequest is raised when a code, refresh token, or grant type
 * parameter is not provided, but was required.
 */
class InvalidRequest extends OAuthBase
{
}
