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
use Symfony\Component\HttpFoundation\Request;

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
   * The original image before any effects are applied.
   *
   * @var \Drupal\Core\Image\ImageInterface
   */
  protected $originalImage;

  /**
   * Focal point manager object.
   *
   * @var \Drupal\focal_point\FocalPointManager
   */
  protected $focalPointManager;

  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  public $request;

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
   * @param \Drupal\focal_point\FocalPointManager $focal_point_manager
   *   Focal point manager.
   * @param \Drupal\crop\CropStorageInterface $crop_storage
   *   Crop storage.
   * @param \Drupal\Core\Config\ImmutableConfig $focal_point_config
   *   Focal point configuration object.
   * @param \Symfony\Component\HttpFoundation\Request
   *   Current request object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, FocalPointManager $focal_point_manager, CropStorageInterface $crop_storage, ImmutableConfig $focal_point_config, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
    $this->focalPointManager = $focal_point_manager;
    $this->cropStorage = $crop_storage;
    $this->focalPointConfig = $focal_point_config;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   *
   * @codeCoverageIgnore
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('image'),
      new FocalPointManager($container->get('entity_type.manager')),
      $container->get('entity_type.manager')->getStorage('crop'),
      $container->get('config.factory')->get('focal_point.settings'),
      \Drupal::request()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    // @todo: Get the original image in case there are multiple scale/crop effects?
    $this->originalImage = clone $image;
    return TRUE;
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
    $crop_size = $crop->size();
    $image_size = [
      'width' => $image->getWidth(),
      'height' => $image->getHeight(),
    ];

    // Check if we are generating a preview image. If so get the focal point
    // from the query parameter, otherwise use the crop position.
    $preview_value = $this->getPreviewValue();
    if (is_null($preview_value)) {
      $focal_point = $crop->position();
    }
    else {
      // @todo: should we check that preview_value is valid here? If its invalid it gets converted to 0,0.
      list($x, $y) = explode('x', $preview_value);
      $focal_point = $this->focalPointManager->relativeToAbsolute($x, $y, $this->originalImage->getWidth(), $this->originalImage->getHeight());
    }

    $focal_point['x'] = (int) round($focal_point['x'] / $this->originalImage->getWidth() * $image_size['width']);
    $focal_point['y'] = (int) round($focal_point['y'] / $this->originalImage->getHeight() * $image_size['height']);

    // The anchor must be the top-left coordinate of the crop area but the focal
    // point is expressed as the center coordinates of the crop area.
    $anchor = [
      'x' => (int) ($focal_point['x'] - ($crop_size['width'] / 2)),
      'y' => (int) ($focal_point['y'] - ($crop_size['height'] / 2)),
    ];

    // Ensure that the crop area doesn't fall off the bottom right of the image.
    $anchor['x'] = $anchor['x'] + $crop_size['width'] <= $image_size['width'] ? $anchor['x'] : $image_size['width'] - $crop_size['width'];
    $anchor['y'] = $anchor['y'] + $crop_size['height'] <= $image_size['height'] ? $anchor['y'] : $image_size['height'] - $crop_size['height'];

    // Ensure that the crop area doesn't fall off the top left of the image.
    $anchor['x'] = max(0, $anchor['x']);
    $anchor['y'] = max(0, $anchor['y']);

    return $anchor;
  }

  /**
   * Set original image.
   *
   * @param \Drupal\Core\Image\ImageInterface $image
   */
  public function setOriginalImage(ImageInterface $image) {
    $this->originalImage = $image;
  }

  /**
   * Get original image.
   *
   * @return \Drupal\Core\Image\ImageInterface
   */
  public function getOriginalImage() {
    return $this->originalImage;
  }

  /**
   * Get the 'focal_point_preview_value' query string value.
   *
   * @return string|NULL
   *
   * @codeCoverageIgnore
   */
  protected function getPreviewValue() {
    return $this->request->query->get('focal_point_preview_value');
  }
}
