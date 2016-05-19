<?php

namespace Drupal\focal_point;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\crop\CropInterface;
use Drupal\crop\CropStorageInterface;
use Drupal\crop\Entity\Crop;
use Drupal\image\Plugin\ImageEffect\ResizeImageEffect;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a base class for image effects.
 */
abstract class FocalPointEffectBase extends ResizeImageEffect implements ContainerFactoryPluginInterface {

  /**
   * Crop storage.
   *
   * @var \Drupal\crop\CropStorageInterface
   */
  protected $cropStorage;

  /**
   * Focal point configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $focalPointConfig;

  /**
   * Constructs a \Drupal\focal_point\FocalPointEffectBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   Image logger.
   * @param \Drupal\crop\CropStorageInterface $crop_storage
   *   Crop storage.
   * @param \Drupal\Core\Config\ImmutableConfig $focal_point_config
   *   Focal point configuration object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, CropStorageInterface $crop_storage, ImmutableConfig $focal_point_config) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->cropStorage = $crop_storage;
    $this->focalPointConfig = $focal_point_config;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      $container->get('entity_type.manager')->getStorage('crop'),
      $container->get('config.factory')->get('focal_point.settings')
    );
  }

  /**
   * Calculate the resize dimensions of an image.
   *
   * The calculated dimensions are based on the longest crop dimension (length
   * or width) so that the aspect ratio is preserved in all cases and that there
   * is always enough image available to the crop.
   *
   * @param int $image_width
   *   Image width.
   * @param int $image_height
   *   Image height.
   * @param int $crop_width
   *   Crop width.
   * @param int $crop_height
   *   Crop height.
   *
   * @return array $resize_data
   *   Resize data.
   */
  public static function calculateResizeData($image_width, $image_height, $crop_width, $crop_height) {
    $resize_data = array();

    if ($crop_width > $crop_height) {
      $resize_data['width'] = (int) $crop_width;
      $resize_data['height'] = (int) ($crop_width * $image_height / $image_width);

      // Ensure there is enough area to crop.
      if ($resize_data['height'] < $crop_height) {
        $resize_data['width'] = (int) ($crop_height * $resize_data['width'] / $resize_data['height']);
        $resize_data['height'] = (int) $crop_height;
      }
    }
    else {
      $resize_data['width'] = (int) ($crop_height * $image_width / $image_height);
      $resize_data['height'] = (int) $crop_height;

      // Ensure there is enough area to crop.
      if ($resize_data['width'] < $crop_width) {
        $resize_data['height'] = (int) ($crop_width * $resize_data['height'] / $resize_data['width']);
        $resize_data['width'] = (int) $crop_width;
      }
    }

    return $resize_data;
  }

  /**
   * Applies the crop effect to an image.
   *
   * @param ImageInterface $image
   *   The image resource to crop.
   *
   * @return bool
   *   TRUE if the image is successfully cropped, otherwise FALSE.
   */
  public function applyCrop(ImageInterface $image) {
    $crop_type = $this->focalPointConfig->get('crop_type');

    /** @var \Drupal\crop\CropInterface $crop */
    if ($crop = Crop::findCrop($image->getSource(), $crop_type)) {
      // An existing crop has been found; set the size.
      $crop->setSize($this->configuration['width'], $this->configuration['height']);
    }
    else {
      // No existing crop could be found; create a new one using the size.
      $crop = $this->cropStorage->create([
        'type' => $crop_type,
        'x' => (int) round($image->getWidth() / 2),
        'y' => (int) round($image->getHeight() / 2),
        'width' => $this->configuration['width'],
        'height' => $this->configuration['height'],
      ]);
    }

    $anchor = $this->calculateAnchor($image, $crop);
    if (!$image->crop($anchor['x'], $anchor['y'], $this->configuration['width'], $this->configuration['height'])) {
      $this->logger->error(
        'Focal point scale and crop failed while scaling and cropping using the %toolkit toolkit on %path (%mimetype, %dimensions, anchor: %anchor)',
        [
          '%toolkit' => $image->getToolkitId(),
          '%path' => $image->getSource(),
          '%mimetype' => $image->getMimeType(),
          '%dimensions' => $image->getWidth() . 'x' . $image->getHeight(),
          '%anchor' => $anchor,
        ]
      );
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Calculate the top left coordinates of crop rectangle.
   *
   * This is based on Crop's anchor function with additional logic to ensure
   * that crop area doesn't fall outside of the original image. Note that the
   * image modules crop effect expects the top left coordinate of the crop
   * rectangle.
   *
   * @param \Drupal\Core\Image\ImageInterface $image
   *   Image object representing original image.
   * @param \Drupal\crop\CropInterface $crop
   *   Crop entity.
   *
   * @return array
   *   Array with two keys (x, y) and anchor coordinates as values.
   */
  protected function calculateAnchor(ImageInterface $image, CropInterface $crop) {
    $original_anchor = $crop->anchor();
    $new_anchor = $original_anchor;

    $image_size = [
      'width' => $image->getWidth(),
      'height' => $image->getHeight(),
    ];

    // Set the minimum number of pixels that must exist between the edge of the
    // image and the anchor point (in both the x and y directions).
    $crop_size = $crop->size();
    $anchor_min_margin_x = (int) ceil($crop_size['width'] / 2);
    $anchor_min_margin_y = (int) ceil($crop_size['height'] / 2);

    // Ensure that the crop area doesn't fall off the bottom right of the image.
    $new_anchor['x'] = ($original_anchor['x'] + $anchor_min_margin_x > $image_size['width']) ? $image_size['width'] - $crop_size['width'] : $original_anchor['x'] - $anchor_min_margin_x;
    $new_anchor['y'] = ($original_anchor['y'] + $anchor_min_margin_y > $image_size['height']) ? $image_size['height'] - $crop_size['height'] : $original_anchor['y'] - $anchor_min_margin_y;

    // Ensure that the crop area doesn't fall off the top left of the image.
    $new_anchor['x'] = max(0, $new_anchor['x']);
    $new_anchor['y'] = max(0, $new_anchor['y']);

    return $new_anchor;
  }

}
