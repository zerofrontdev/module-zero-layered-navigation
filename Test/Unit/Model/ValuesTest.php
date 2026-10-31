<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Test\Unit\Model;

use PHPUnit\Framework\TestCase;
use Zero\LayeredNavigation\Model\Values;

class ValuesTest extends TestCase
{
    public function testParse(): void
    {
        $this->assertSame(['49', '50'], Values::parse('49, 50,,49'));
        $this->assertSame(['7'], Values::parse(['7', '']));
        $this->assertSame([], Values::parse(null));
    }

    public function testToggleAddsAndTakesOut(): void
    {
        $this->assertSame('49,50', Values::toggle(['49'], '50'));
        $this->assertSame('50', Values::toggle(['49', '50'], '49'));
        $this->assertNull(Values::toggle(['49'], '49'));
    }

    public function testNestedArraysAreSkipped(): void
    {
        $this->assertSame(['7', '9'], Values::parse(['7', ['8'], ['a' => ['b' => '1']], '9']));
        $this->assertSame([], Values::parse(['a' => ['b' => '1']]));
        $this->assertSame([], Values::parse(new \stdClass()));
        $this->assertSame(['1', '0'], Values::parse([1, true, 0, false]));
    }
}
