<?php

declare(strict_types=1);

namespace Nowo\DoctrineEncryptBundle\Exception;

use Throwable;

/**
 * Marker interface for the exceptions thrown by this bundle (catch it to handle every bundle failure at once).
 */
interface ExceptionInterface extends Throwable
{
}
