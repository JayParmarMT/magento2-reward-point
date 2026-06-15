<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Thrown when a subtraction would bring balance below zero
 */
class InsufficientBalanceException extends LocalizedException
{
}
