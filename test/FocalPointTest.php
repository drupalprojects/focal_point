<?php

include_once __DIR__ . './../focal_point.module';
include_once __DIR__ . './../focal_point.effects.inc';

class focalPointTest extends PHPUnit_Framework_TestCase {

  /**
   * @dataProvider fieldSupportedProvider
   */
  public function testFieldSupported($field_type, $instance_type, $is_enabled, $require_enabled, $expected) {
    $field = array(
      'type' => $field_type,
    );
    $instance = array(
      'settings' => array('focal_point_enabled' => $is_enabled),
      'widget' => array('type' => $instance_type),
    );

    $this->assertSame($expected, _focal_point_field_supported($field, $instance, $require_enabled));
  }

  public function fieldSupportedProvider() {
    return array(
      array('image', 'image_image', TRUE, TRUE, TRUE),
      array('image', 'image_image', FALSE, TRUE, FALSE),
      array('image', 'not_image', TRUE, TRUE, FALSE),
      array('not_image', 'image_image', TRUE, TRUE, FALSE),
    );
  }

  /**
   * @dataProvider parseFocalPointProvider
   */
  public function testFocalPoint($focal_point, $expected) {
    $this->assertSame($expected, focal_point_parse($focal_point));
  }

  public function parseFocalPointProvider() {
    return array(
      array('23,56', array('x-offset' => '23', 'y-offset' => '56')),
      array('56,23', array('x-offset' => '56', 'y-offset' => '23')),
      array('0,0', array('x-offset' => '0', 'y-offset' => '0')),
      array('100,100', array('x-offset' => '100', 'y-offset' => '100')),
      array('', array('x-offset' => '50', 'y-offset' => '50')),
    );
  }

  /**
   * @dataProvider calculateEffectAnchorProvider
   */
  public function testCalculateEffectAnchor($image_size, $crop_size, $focal_point_offset, $expected) {
    $this->assertSame($expected, focal_point_effect_calculate_anchor($image_size, $crop_size, $focal_point_offset));
  }

  public function calculateEffectAnchorProvider() {
    return array(
      array(640, 300, 50, 170),
      array(640, 300, 80, 340),
      array(640, 300, 10, 0),
      array(640, 640, 640, 0),
      array(640, 800, 50, -80),
      array(640, 800, 80, -80),
      array(640, 800, 10, -80),
    );
  }

  /**
   * @dataProvider resizeDataProvider
   */
  public function testResizeData($image_width, $image_height, $crop_width, $crop_heigt, $expected) {
    $this->assertSame($expected, focal_point_effect_resize_data($image_width, $image_height, $crop_width, $crop_heigt));
  }

  public function resizeDataProvider() {
    return array(
      array(640,480,300,100,array('width' => 300, 'height' => 225)), // Horizontal image with horizontal crop
      array(640,480,100,300,array('width' => 400, 'height' => 300)), // Horizontal image with vertical crop
      array(480,640,300,100,array('width' => 300, 'height' => 400)), // Vertical image with horizontal crop
      array(480,640,100,300,array('width' => 225, 'height' => 300)), // Vertical image with vertical crop
      array(640,480,3000,1000,array('width' => 3000, 'height' => 2250)), // Horizontal image with too large crop
      array(1920,1080,400,300,array('width' => 533, 'height' => 300)), // Image would be too small to crop after resize
    );
  }

}
