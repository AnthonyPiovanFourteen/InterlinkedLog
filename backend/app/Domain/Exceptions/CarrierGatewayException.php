<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/** Falha ao cotar numa transportadora. Isola a falha: as demais seguem. */
class CarrierGatewayException extends RuntimeException {}
