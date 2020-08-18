<?php

namespace Stripe;

defined( 'ABSPATH' ) || die();

// For backwards compatibility, the `File` class is aliased to `FileUpload`.
class_alias('Stripe\\File', 'Stripe\\FileUpload');
