<?php

declare(strict_types=1);

namespace BlackBonjour\TableGateway;

use Doctrine\DBAL\Exception;
use Error;

final class ResultException extends Error implements Exception {}
