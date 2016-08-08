<?php

namespace Drupal\focal_point\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\image\Entity\ImageStyle;
use Drupal\file\Entity\File;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Class FocalPointPreviewController.
 *
 * @package Drupal\focal_point\Controller
 */
class FocalPointPreviewController extends ControllerBase {

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\Validator\Exception\InvalidArgumentException
   */
  public function content($fid, $focal_point_value) {
    $output = [];

    $file = File::load($fid);
    $image = \Drupal::service('image.factory')->get($file->getFileUri());
    if (!$image->isValid()) {
      throw new InvalidArgumentException('The file with id = $fid is not an image.');
    }

    $styles = $this->getFocalPointImageStyles();

    // Since we are about to create a new preview of this image, we first must
    // flush the old one. This should not be a performance hit since there is
    // no good reason for anyone to preview an image unless they are changing
    // the focal point value.
    image_path_flush($image->getSource());

    $derivative_images = [];
    $derivative_image_note = '';

    $original_image = [
      '#theme' => 'image',
      '#uri' => $image->getSource(),
      '#alt' => t('Focal Point Preview Image'),
      '#attributes' => [
        'id' => 'focal-point-preview-image',
      ],
    ];

    if (!empty($styles)) {
      foreach ($styles as $style) {
        $style_label = $style->get('label');
        $url = $this->buildUrl($style, $file, $focal_point_value);

        $derivative_images[$style->getName()] = [
          'style' => $style_label,
          'url' => $url,
          'image' => [
            '#theme' => 'image',
            '#uri' => $url,
            '#alt' => $this->t('Focal Point Preview: %label', array('%label' => $style_label)),
            '#attributes' => [
              'class' => ['focal-point-derivative-preview-image'],
            ],
          ],
        ];
      }
      $derivative_image_note = $this->t('Click an image to see a larger preview. You may need to scroll horizontally for more image styles.');
    }
    else {
      // There are no styles that use a focal point effect to preview.
      drupal_set_message($this->t('You must have at least one <a href=":url">image styles</a> defined that uses a focal point effect in order to preview.', array(':url' => '/admin/config/media/image-styles')), 'warning');
    }

    $output['focal_point_preview_page'] = array(
      '#theme' => "focal_point_preview_page",
      "#original_image" => $original_image,
      '#derivative_images' => $derivative_images,
      '#focal_point' => $focal_point_value,
      '#preview_image_note' => $this->t('This preview image above may have been scaled to fit on the page.'),
      '#derivative_image_note' => $derivative_image_note,
    );

    return $output;
  }

  /**
   * Deny users access to the preview page unless they have permission to edit
   * an entity (any entity) that references the current image being previewed.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param $fid
   *   The file id for the image being previewed from the URL.
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function access(AccountInterface $account, $fid) {
    $access = AccessResult::forbidden();

    // @todo: I should be able to use "magic args" to load the file directly.
    $file = File::load($fid);
    $image = \Drupal::service('image.factory')->get($file->getFileUri());
    if (!$image->isValid()) {
      throw new InvalidArgumentException('The file with id = $fid is not an image.');
    }

    if (function_exists('file_get_file_references')) {
      $references = file_get_file_references($file, NULL, EntityStorageInterface::FIELD_LOAD_REVISION, '');
      foreach ($references as $field_name => $data) {
        foreach (array_keys($data) as $entity_type_id) {
          if ($account->hasPermission($entity_type_id . ".edit")) {
            $access = AccessResult::allowed();
            break;
          }
        }
      }

    }

    return $access;
  }

  /**
   * Build a list of image styles that include an effect defined by focal point.
   *
   * @return array
   */
  public function getFocalPointImageStyles() {
    // @todo: Can this be generated? See $imageEffectManager->getDefinitions();
    $focal_point_effects = array('focal_point_crop', 'focal_point_scale_and_crop');

    $styles_using_focal_point = array();
    $styles = $this->entityTypeManager()->getStorage('image_style')->loadMultiple();
    foreach ($styles as $image_style_id => $style) {
      foreach ($style->getEffects() as $effect) {
        $style_using_focal_point = in_array($effect->getPluginId(), $focal_point_effects, TRUE);
        if ($style_using_focal_point) {
          $styles_using_focal_point[$image_style_id] = $style;
          break;
        }
      }
    }

    return $styles_using_focal_point;
  }

  /**
   * Create the URL for a preview image including a query parameter.
   *
   * @param \Drupal\image\Entity\ImageStyle $style
   *   The image style being previewed.
   *
   * @param \Drupal\file\Entity\File $image
   *   The image being previewed.
   *
   * @param $focal_point_value
   *   The focal point being previewed in the format XxY where x and y are the
   *   left and top offsets in percentages.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   */
  protected function buildUrl(ImageStyle $style, File $image, $focal_point_value) {
    $url = $style->buildUrl($image->getFileUri());
    $url .= (strpos($url, '?') !== FALSE ? '&' : '?') . 'focal_point_preview_value=' . $focal_point_value;

    return $url;
  }
}
