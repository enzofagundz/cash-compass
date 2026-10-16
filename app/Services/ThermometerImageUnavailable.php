<?php

namespace App\Services;

use RuntimeException;

/**
 * Signals that the thermometer image could not be produced (missing
 * Cloudflare credentials or an unexpected API response).
 */
class ThermometerImageUnavailable extends RuntimeException {}
