<?php

namespace App\Services\Security;

use RuntimeException;

class SsrfBlockedException extends RuntimeException
{
}
