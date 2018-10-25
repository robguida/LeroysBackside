<?php
/**
 * Created by PhpStorm.
 * User: rob
 * Date: 10/11/2018
 * Time: 9:48 PM
 */

namespace LeroyTest\LeType;

use Exception;
use Leroy\LeType\LeInt;
use LeroyTestLib\LeroyUnitTestAbstract;

class LeIntTest extends LeroyUnitTestAbstract
{
    public function testLeInt()
    {
        $values = ['string', 'test', 'leint', pow(2, 63)];
        for ($i = 0; $i < 10; $i++) {
            $values[] = rand(LeInt::getMin(), LeInt::getMax());
        }
        $i = 0;
        foreach ($values as $value) {
            try {
                $number = LeInt::set($value);
                $this->assertInstanceOf('Leroy\LeType\LeInt', $number);
                $this->assertEquals($value, $number->get());
            } catch (Exception $e) {
                if (2 >= $i) {
                    $this->assertEquals("{$value} is not numeric", $e->getMessage());
                } else {
                    $this->assertEquals("{$value} cannot be greater than " . LeInt::getMax(), $e->getMessage());
                }
            }
            $i++;
        }
    }

    public function testLeIntValidate()
    {
        $values = ['string', 'test', 'leint', pow(2, 63)];
        foreach ($values as $value) {
            $number = LeInt::verify($value);
            $this->assertFalse($number);
        }
        $values = [];
        for ($i = 0; $i < 10; $i++) {
            $values[] = rand(LeInt::getMin(), LeInt::getMax());
        }
        foreach ($values as $value) {
            $number = LeInt::verify($value);
            $this->assertInstanceOf('Leroy\LeType\LeInt', $number);
        }
    }
}
