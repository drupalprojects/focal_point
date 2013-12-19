<?php

/**
 * @file
 * Functionality needed by the focal point admin pages.
 */

/**
 * System form for focal point configuration settings.
 */
function focal_point_configuration_form($form, &$form_state) {
  $options = array(
    'image' => t('Standard image fields'),
    'media' => t('Media module image fields'),
  );
  $form['focal_point_enabled_for'] = array(
    '#type' => 'checkboxes',
    '#title' => 'Enable focal point for the following:',
    '#options' => $options, // @todo: consider allowing this to be altered
    '#default_value' => variable_get('focal_point_enabled_for', array_keys($options)),
  );

  $form['focal_point_preview_image_style'] = array(
    '#type' => 'select',
    '#title' => 'Preview Image Style',
    '#options' => image_style_options(), // @todo: consider filtering out styles that use focus and styles that crop the image. If we do, adjust the description accordingly.
    '#default_value' => variable_get('focal_point_preview_image_style', 'thumbnail'),
    '#description' => t('When no preview image exists already, choose which image style should be used when setting the focal point.'),
  );

  $form['focal_point_test_drive_link'] = [
    '#type' => 'markup',
    '#markup' => l(t('Take a test drive'), 'focal_point/test-drive'),
  ];

  return system_settings_form($form);
}

/**
 * Form builder for the "test drive" page.
 */
function focal_point_test_drive_form($form, &$form_state) {
  $example = variable_get('focal_point_test_drive_image', NULL);
  $file = !is_null($example) ? file_load($example) : NULL;

  $form['test_drive_image_upload'] = array(
    '#type' => 'managed_file',
    '#name' => 'test_drive_image',
    '#title' => t('Choose an image to take for a test drive'),
    '#default_value' => '',
    '#description' => t("You will have the opportunity to set the focal point for this image to see how this module works."),
    '#required' => FALSE,
    '#upload_location' => 'public://focal_point/'
  );

  // There is already an example image set (and it hasn't been removed).
  if (!empty($example) && $file) {
    // Tweak the file upload widget.
    $form['test_drive_image_upload']['#title'] = t('Choose a different image to take for a test drive');
    $form['test_drive_image_upload']['#description'] = '';

    // Display the image and include the focal point indicator and field.
    $form['test_drive_image'] = array(
      '#theme_wrappers' => array('container'),
      'test_drive_indicator' => array(
        '#markup' => _focal_point_indicator('test-drive'),
      ),
      'test_drive_widget_image' => array(
        '#type' => 'markup',
        '#theme' => 'image_style',
        '#style_name' => variable_get('focal_point_preview_image_style', 'thumbnail'),
        '#path' => $file->uri,
      ),
      'focal_point_test_drive_focal_point' => _focal_point_field('test-drive', 0, variable_get('focal_point_test_drive_focal_point', '50,50')),
    );
  }

  $form['test_drive_submit'] = array(
    '#type' => 'submit',
    '#value' => t('Set the focal point'),
  );

  // Using the example image, show every image style that uses a focal point
  // effect.
  if (!empty($example)) {
    $form['focal_point_examples'] = array(
      '#theme_wrappers' => array('container'),
      '#attributes' => array(
        'class' => array('focal_point_examples'),
      ),
    );
    $styles = _focal_point_get_image_styles();
    if (!empty($styles) && $file ) {
      foreach ($styles as $isid => $style) {
        $form['focal_point_examples'][$isid] = array(
          '#type' => 'markup',
          '#prefix' => '<h2>' . $style['loaded_style']['name'] . '</h2>',
          '#theme' => 'image_style',
          '#style_name' => $style['loaded_style']['name'],
          '#path' => $file->uri,
          '#theme_wrappers' => array('container'),
        );
      }
    }
    else {
      $form['focal_point_examples']['#markup'] = '<div class="messages warning">' . t('There are no styles defined that use a focal point effect. To see how this module will work you should <a href="@url">create a new image style</a> that uses one.', array('@url' => '/admin/config/media/image-styles')) . '</div>';
    }
  }

  $form['#submit'][] = 'focal_point_test_drive_form_submit';

  return $form;
}

/**
 * Form submit handler for focal_point_test_drive_form().
 *
 * @see focal_point_test_drive_form().
 */
function focal_point_test_drive_form_submit(&$form, &$form_state) {
  // Save the 'test_drive_image' variable.
  $validators = array(
    'file_validate_is_image' => array(),
  );
  $file = file_save_upload('test_drive_image_upload', $validators);
  if ($file) {
    variable_set('focal_point_test_drive_image', $file->fid);
    variable_del('focal_point_test_drive_focal_point');
  }

  // Save the test drive focal point setting.
  if (isset($form_state['values']['focal_point_test_drive_focal_point'])) {
    $old_value = variable_get('focal_point_test_drive_focal_point', '50,50');
    if ($old_value !== $form_state['values']['focal_point_test_drive_focal_point']) {
      variable_set('focal_point_test_drive_focal_point', $form_state['values']['focal_point_test_drive_focal_point']);

      // Delete old derivative images since the focal point changed.
      $fid = variable_get('focal_point_test_drive_image', NULL);
      $file = !is_null($fid) ? file_load($fid) : NULL;
      if ($file) {
        image_path_flush($file->uri);
      }
    }
  }
}
