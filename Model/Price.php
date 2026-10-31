<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Model;

use Magento\Framework\App\RequestInterface;

/**
 * The price range asked for: Magento's own `price=100-500`, or the price
 * form's `price_from` / `price_to` (either may be empty).
 */
class Price
{
    /**
     * @return array{0: ?float, 1: ?float}|null
     */
    public static function range(RequestInterface $request): ?array
    {
        $from = self::number($request->getParam('price_from'));
        $to = self::number($request->getParam('price_to'));
        if ($from === null && $to === null) {
            $param = $request->getParam('price');
            if (!is_string($param) || !preg_match('/^(\d*\.?\d*)-(\d*\.?\d*)\z/', explode(',', $param)[0], $m)) {
                return null;
            }
            $from = self::number($m[1]);
            $to = self::number($m[2]);
        }
        if ($from === null && $to === null) {
            return null;
        }
        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }

    /**
     * A non-negative finite number, or null (an array, "abc", "1e999", "-5").
     */
    private static function number(mixed $value): ?float
    {
        if (!is_scalar($value)) {
            return null;
        }
        $value = str_replace([',', ' '], ['.', ''], trim((string) $value));
        if (!is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) && $number >= 0 ? $number : null;
    }
}
