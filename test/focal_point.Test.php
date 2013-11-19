<?php

include_once __DIR__ . './../focal_point.module';

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
}
