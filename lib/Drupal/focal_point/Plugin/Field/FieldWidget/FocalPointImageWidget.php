<?php

/**
 * @file
 * Contains \Drupal\focal_point\Plugin\Field\FieldWidget\FocalPointImageWidget.
 */

namespace Drupal\focal_point\Plugin\Field\FieldWidget;

use Drupal\focal_point\FocalPoint;
use Drupal\image\Plugin\Field\FieldWidget\ImageWidget;
use Drupal\Component\Utility\NestedArray;

/**
 * Plugin implementation of the 'image_fp' widget.
 *
 * @FieldWidget(
 *   id = "image_fp",
 *   label = @Translation("Image (focal point)"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class FocalPointImageWidget extends ImageWidget {

  /**
   * Form API callback: Processes a image_fp field element.
   *
   * Expands the image_fp type to include the focal_point field.
   *
   * This method is assigned as a #process callback in formElement() method.
   */
  public static function process($element, &$form_state, $form) {
    $element = parent::process($element, $form_state, $form);

    $item = $element['#value'];
    $item['fids'] = $element['fids']['#value'];

    // Add the focal point indicator to preview.
    if (isset($element['preview'])) {
      $preview = array(
        'indicator' => array(
          '#theme_wrappers' => array('container'),
          '#attributes' => array(
            'class' => array('focal-point-indicator'),
            'data-field-name' => $element['#field_name'],
            'data-delta' => $element['#delta'],
          ),
          '#markup' => '',
        ),
        'thumbnail' => $element['preview'],
      );
      $element['preview'] = $preview;
    }

    // Add the focal point field.
    $element['focal_point'] = array(
      '#type' => 'textfield',
      '#title' => 'Focal point',
      '#description' => t('Specify the focus of this image in the form "leftoffset,topoffset" where offsets are in percents. Ex: 25,75'),
      '#default_value' => isset($item['focal_point']) ? $item['focal_point'] : FocalPoint::DEFAULT_VALUE,
      '#element_validate' => array('\Drupal\focal_point\Plugin\Field\FieldWidget\FocalPointImageWidget::validateFocalPoint'),
      '#attributes' => array(
        'class' => array('focal-point', 'focal-point-' . $element['#field_name'] . '-' . $element['#delta']),
        'data-delta' => $element['#delta'],
        'data-field-name' => $element['#field_name'],
      ),
      '#attached' => array(
        'library' => array('focal_point/drupal.focal_point'),
      ),
    );

    return $element;
  }

  /**
   * Form API callback. Retrieves the value for the file_generic field element.
   *
   * This method is assigned as a #value_callback in formElement() method.
   */
  public static function value($element, $input = FALSE, $form_state) {
    $return = parent::value($element, $input, $form_state);

    // When an element is loaded, focal_point needs to be set. During a form
    // submission the value will already be there.
    if (!array_key_exists('focal_point', $return)) {
      $return['focal_point'] = FocalPoint::get($return['target_id']);
    }
    return $return;
  }

  /**
   * Validate callback for the focal point field.
   */
  public static function validateFocalPoint($element, &$form_state) {
    // Only do validation if the function is triggered from other places than
    // the image process form.
    if (!in_array('file_managed_file_submit', $form_state['triggering_element']['#submit'])) {
      // If the image is not there, we do not check for empty values.
      $parents = $element['#parents'];
      $field = array_pop($parents);
      $image_field = NestedArray::getValue($form_state['input'], $parents);

      // We check for the array key, so that it can be NULL (like if the user
      // submits the form without using the "upload" button).
      if (!array_key_exists($field, $image_field)) {
        return;
      }
      // Check if the field is valid.
      elseif (!FocalPoint::validate($image_field[$field])) {
        \Drupal::formBuilder()->setError($element, $form_state, t('The !title field should be in the form "leftoffset,topoffset" where offsets are in percents. Ex: 25,75.', array('!title' => $element['#title'])));
        return;
      }
    }
  }

}
