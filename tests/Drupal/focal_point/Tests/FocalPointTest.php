<?php

/**
 * @file
 * Contains \Drupal\focal_point\Tests\FocalPointTest.
 */

namespace Drupal\focal_point\Tests;

use Drupal\Tests\UnitTestCase;
use Drupal\focal_point\FocalPoint;

/**
 * Tests the Focal Point basic functionality.
 *
 * @group Focal Point
 * @group Drupal
 *
 * @see \Drupal\focal_point\FocalPoint
 */
class FocalPointTest extends UnitTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Focal Point',
      'description' => 'Tests the focal point basic functionality.',
      'group' => 'FocalPoint',
    );
  }

  /**
   * Tests the parse() method.
   *
   * @dataProvider providerParseFocalPoint
   */
  public function testFocalPointParse($focal_point, $expected) {
    $this->assertSame($expected, FocalPoint::parse($focal_point));
  }

  /**
   * Data provider for testFocalPoint().
   */
  public function providerParseFocalPoint() {
    return array(
      array('23,56', array('x-offset' => '23', 'y-offset' => '56')),
      array('56,23', array('x-offset' => '56', 'y-offset' => '23')),
      array('0,0', array('x-offset' => '0', 'y-offset' => '0')),
      array('100,100', array('x-offset' => '100', 'y-offset' => '100')),
      array('', array('x-offset' => '50', 'y-offset' => '50')),
      array('invalid', array('x-offset' => '50', 'y-offset' => '50')),
    );
  }

  /**
   * Tests the validate() method.
   *
   * @dataProvider providerValidateFocalPoint
   */
  public function testFocalPointValidate($focal_point, $expected) {
    $this->assertSame($expected, FocalPoint::validate($focal_point));
  }

  /**
   * Data provider for testFocalPoint().
   */
  public function providerValidateFocalPoint() {
    return array(
      array('50,50', TRUE),
      array('75,25', TRUE),
      array('3,50', TRUE),
      array('83,6', TRUE),
      array('2,9', TRUE),
      array('100,100', TRUE),
      array('0,0', TRUE),
      array('100,0', TRUE),
      array('-20,50', FALSE),
      array('18,-3', FALSE),
      array('44,101', FALSE),
      array('', FALSE),
      array('invalid', FALSE),
    );
  }

}
