<?php

/**
 * @file
 * Contains \Drupal\focal_point\Tests\FocalPointEffectsTest.
 */

namespace Drupal\focal_point\Tests;

use Drupal\Tests\UnitTestCase;
use Drupal\focal_point\FocalPointEffectBase;

/**
 * Tests the Focal Point image effects.
 *
 * @group Focal Point
 * @group Drupal
 *
 * @see \Drupal\focal_point\FocalPointEffectBase
 */
class FocalPointEffectsTest extends UnitTestCase {

  public static function getInfo() {
    return array(
      'name' => 'Focal Point Effects',
      'description' => 'Tests the focal point image effects.',
      'group' => 'FocalPoint',
    );
  }

  /**
   * @dataProvider calculateAnchorProvider
   */
  public function testCalculateAnchor($image_size, $crop_size, $focal_point_offset, $expected) {
    $this->assertSame($expected, FocalPointEffectBase::calculateAnchor($image_size, $crop_size, $focal_point_offset));
  }

  /**
   * Data provider for calculateAnchorProvider().
   *
   * @see FocalPointEffectsTest::calculateAnchorProvider()
   */
  public function calculateAnchorProvider() {
    return array(
      array(640, 300, 50, 170),
      array(640, 300, 80, 340),
      array(640, 300, 10, 0),
      array(640, 640, 640, 0),
      array(640, 800, 50, 0),
    );
  }

}
