<?php

/**
 * @file
 * Functionality needed by the focal point admin pages.
 */

function focal_point_configuration_form($form, &$form_state) {
  $form['focal_point_preview_image_style'] = array(
    '#type' => 'select',
    '#title' => 'Preview Image Style',
    '#options' => image_style_options(), // @todo: consider filtering out styles that use focus and styles that crop the image. If we do, adjust the description accordingly.
    '#default_value' => variable_get('focal_point_preview_image_style', 'thumbnail'),
    '#description' => t('When no preview image exists already, choose which image style should be used when setting the focal point.'),
  );

  return system_settings_form($form);
}
