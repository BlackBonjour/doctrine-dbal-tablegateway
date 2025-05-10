<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway\Exception;

use Doctrine\DBAL\Exception;
use Error;

final class InvalidArgumentException extends Error implements Exception {}
