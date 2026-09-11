<?php

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testMath(): void
    {
        $this->assertSame(2, 1 + 1);
    }
}
