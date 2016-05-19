<?php

/**
 * @file
 * Contains \Drupal\focal_point\Tests\FocalPointEffectsTest.
 */

namespace Drupal\Tests\focal_point\Unit\Effects;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Image\ImageInterface;
use Drupal\crop\CropInterface;
use Drupal\crop\CropStorageInterface;
use Drupal\focal_point\Plugin\ImageEffect\FocalPointCropImageEffect;
use Drupal\Tests\UnitTestCase;
use Drupal\focal_point\FocalPointEffectBase;
use Psr\Log\LoggerInterface;

/**
 * Tests the Focal Point image effects.
 *
 * @group Focal Point
 *
 * @coversDefaultClass \Drupal\focal_point\FocalPointEffectBase
 */
class FocalPointEffectsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * @covers ::calculateResizeData
   *
   * @dataProvider calculateResizeDataProvider
   */
  public function testCalculateResizeData($image_width, $image_height, $crop_width, $crop_height, $expected) {
    $this->assertSame($expected, FocalPointEffectBase::calculateResizeData($image_width, $image_height, $crop_width, $crop_height));
  }

  /**
   * Data provider for testCalculateResizeData().
   *
   * @see FocalPointEffectsTest::testCalculateResizeData()
   */
  public function calculateResizeDataProvider() {
    $data = [];
    $data['horizontal_image_horizontal_crop'] = [640, 480, 300, 100, ['width' => 300, 'height' => 225]];
    $data['horizontal_image_vertical_crop'] = [640, 480, 100, 300, ['width' => 400, 'height' => 300]];
    $data['vertical_image_horizontal_crop'] = [480, 640, 300, 100, ['width' => 300, 'height' => 400]];
    $data['vertical_image_vertical_crop'] = [480, 640, 100, 300, ['width' => 225, 'height' => 300]];
    $data['horizontal_image_too_large_crop'] = [640, 480, 3000, 1000, ['width' => 3000, 'height' => 2250]];
    $data['image_too_narrow_to_crop_after_resize'] = [1920, 1080, 400, 300, ['width' => 533, 'height' => 300]];
    $data['image_too_short_to_crop_after_resize'] = [200, 400, 1000, 1000, ['width' => 1000, 'height' => 2000]];
    return $data;
  }

  /**
   * @covers ::calculateAnchor
   *
   * @dataProvider calculateAnchorProvider
   */
  public function testCalculateAnchor($image_size, $crop_size, $focal_point_anchor, $expected) {
    $logger = $this->prophesize(LoggerInterface::class);
    $crop_storage = $this->prophesize(CropStorageInterface::class);
    $immutable_config = $this->prophesize(ImmutableConfig::class);

    $effect = new FocalPointCropImageEffect([], 'plugin_id', [], $logger->reveal(), $crop_storage->reveal(), $immutable_config->reveal());

    $image = $this->prophesize(ImageInterface::class);
    $image->getWidth()->willReturn($image_size[0]);
    $image->getHeight()->willReturn($image_size[1]);

    $crop = $this->prophesize(CropInterface::class);
    $crop->anchor()->willReturn([
      'x' => $focal_point_anchor[0],
      'y' => $focal_point_anchor[1],
    ]);
    $crop->size()->willReturn([
      'width' => $crop_size[0],
      'height' => $crop_size[1],
    ]);

    $effect_reflection = new \ReflectionClass(FocalPointCropImageEffect::class);
    $method = $effect_reflection->getMethod('calculateAnchor');
    $method->setAccessible(TRUE);
    $this->assertSame($expected, $method->invokeArgs($effect, [$image->reveal(), $crop->reveal()]));
  }

  /**
   * Data provider for testCalculateAnchor().
   *
   * @see FocalPointEffectsTest::testCalculateAnchor()
   */
  public function calculateAnchorProvider() {
    $data = [];
    $data['crop_fits_within_image_even'] = [[1000, 100], [30, 20], [250, 50], ['x' => 235, 'y' => 40]];
    $data['crop_fits_within_image_odd'] = [[1000, 100], [35, 25], [313, 49], ['x' => 295, 'y' => 36]];
    $data['crop_does_not_fall_off_image_top'] = [[1000, 100], [30, 40], [313, 10], ['x' => 298, 'y' => 0]];
    $data['crop_does_not_fall_off_image_bottom'] = [[1000, 100], [50, 50], [900, 90], ['x' => 875, 'y' => 50]];
    $data['crop_does_not_fall_off_image_left'] = [[1000, 100], [50, 50], [10, 50], ['x' => 0, 'y' => 25]];
    $data['crop_does_not_fall_off_image_right'] = [[1000, 100], [50, 50], [975, 60], ['x' => 950, 'y' => 35]];
    $data['crop_does_not_fall_off_image_top_left'] = [[1000, 100], [50, 50], [0, 0], ['x' => 0, 'y' => 0]];
    $data['crop_does_not_fall_off_image_top_right'] = [[1000, 100], [50, 50], [1000, 5], ['x' => 950, 'y' => 0]];
    $data['crop_does_not_fall_off_image_bottom_left'] = [[1000, 100], [75, 75], [0, 100], ['x' => 0, 'y' => 25]];
    $data['crop_does_not_fall_off_image_bottom_right'] = [[1000, 100], [50, 50], [1000, 100], ['x' => 950, 'y' => 50]];
    return $data;
  }

}
