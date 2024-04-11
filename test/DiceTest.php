<?php

namespace VoidTest;

use PHPUnit\Framework\TestCase;
use Void\Dice;

class DiceTest extends TestCase
{
    private ?Dice $dice;
    protected function setUp(): void
    {
        $this->dice = new Dice();
    }

    public function testMin()
    {
        $this->assertEquals(1, $this->dice->min('1d6'));
        $this->assertEquals(1, $this->dice->min('d6'));
        $this->assertEquals(1, $this->dice->min('d100'));
        $this->assertEquals(2, $this->dice->min('2d20'));
        $this->assertEquals(31, $this->dice->min('1d100+10*3'));
        $this->assertEquals(6, $this->dice->min('6d6'));
        $this->assertEquals(10, $this->dice->min('10*1d4'));

        $this->assertEquals(2, $this->dice->min('1d6', true));
        $this->assertEquals(2, $this->dice->min('d6', true));
        $this->assertEquals(2, $this->dice->min('d100', true));
        $this->assertEquals(4, $this->dice->min('2d20', true));
        $this->assertEquals(32, $this->dice->min('1d100+10*3', true));
        $this->assertEquals(12, $this->dice->min('6d6', true));
        $this->assertEquals(20, $this->dice->min('10*1d4', true));

        $this->assertEquals(3, $this->dice->min('3d6', false, true));
        $this->assertEquals(6, $this->dice->min('3d6', true, true));
    }

    public function testMax()
    {
        $this->assertEquals(6, $this->dice->max('1d6'));
        $this->assertEquals(6, $this->dice->max('d6'));
        $this->assertEquals(100, $this->dice->max('d100'));
        $this->assertEquals(40, $this->dice->max('2d20'));
        $this->assertEquals(130, $this->dice->max('1d100+10*3'));
        $this->assertEquals(36, $this->dice->max('6d6'));
        $this->assertEquals(40, $this->dice->max('10*1d4'));

        $this->assertEquals(6, $this->dice->max('1d6', true));
        $this->assertEquals(6, $this->dice->max('d6', true));
        $this->assertEquals(100, $this->dice->max('d100', true));
        $this->assertEquals(40, $this->dice->max('2d20', true));
        $this->assertEquals(130, $this->dice->max('1d100+10*3', true));
        $this->assertEquals(36, $this->dice->max('6d6', true));
        $this->assertEquals(40, $this->dice->max('10*1d4', true));
    }

    public function testRoll()
    {
        $roll = $this->dice->roll('3d6');
        $this->assertGreaterThanOrEqual(3, $roll);
        $this->assertLessThanOrEqual(18, $roll);

        $roll = $this->dice->roll('3d6', true);
        $this->assertGreaterThanOrEqual(6, $roll);
        $this->assertLessThanOrEqual(18, $roll);

        $roll = $this->dice->roll('3d6', false, true);
        $this->assertGreaterThanOrEqual(4, $roll);
        $this->assertLessThanOrEqual(18, $roll);

    }
}
