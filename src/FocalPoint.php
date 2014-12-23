<?php

/**
 * @file
 * Contains \Drupal\focal_point\FocalPoint.
 */

namespace Drupal\focal_point;

use Drupal\Core\Cache;

/**
 * Abstract class for FocalPoint operations.
 */
abstract class FocalPoint {

  /**
   * The default value to use for focal point when non is specified.
   */
  const DEFAULT_VALUE = '50,50';

  /**
   * Focal point values keyed by fid that have been retrieved during this
   * request.
   *
   * @var array
   */
  private static $focal_point_values = array();

  /**
   * Implements \Drupal\focal_point\FocalPoint::get().
   *
   * Get the focal point value for a given file entity. If none is found, return
   * an empty string.
   *
   * @param int $fid
   *
   * @return string
   */
  public static function get($fid) {
    $result = self::getMultiple(array($fid));
    return isset($result[$fid]) ? $result[$fid] : '';
  }

  /**
   * Implements \Drupal\focal_point\FocalPoint::getMultiple().
   *
   * Get the focal point values in an array keyed by fid for the given file
   * entities. If none is found for any of the given files, the value for that
   * file will be an empty string.
   *
   * @param array $fids
   *
   * @return array
   */
  public static function getMultiple(array $fids) {
    $missing = array_diff($fids, array_keys(self::$focal_point_values));
    if ($missing) {
      $result = db_query('SELECT fid, focal_point FROM {focal_point} WHERE fid IN (:fids)', array(':fids' => $missing))->fetchAllKeyed();
      self::$focal_point_values += $result;
    }

    return array_intersect_key(self::$focal_point_values, array_combine($fids, $fids));
  }

  /**
   * Implements \Drupal\focal_point\FocalPoint::getFromURI().
   *
   * Get the focal point value for a given file based on its URI. If none is
   * found, return an empty string.
   *
   * @param string $uri
   *
   * @return string
   *
   * @todo Figure out a better way of doing this. Right now its needed by the
   *   focal point image effect but it seems wrong.
   */
  public static function getFromURI($uri) {
    $query = db_select('focal_point', 'fp')
      ->fields('fp', array('focal_point'));
    $query->join('file_managed', 'fm', 'fp.fid = fm.fid');
    $query->condition('fm.uri', $uri);
    $focal_point = $query->execute()->fetchField();

    return $focal_point;
  }

  /**
   * Implements \Drupal\focal_point\FocalPoint::save().
   *
   * Save the given focal point value for the given file to the database.
   *
   * @param string $focal_point
   * @param int $fid
   */
  public static function save($focal_point, $fid) {
    $existing_focal_point = self::get($fid);

    // If the focal point has not changed, then there is nothing to see here.
    if ($existing_focal_point == $focal_point) {
      return;
    }

    // Create, update or delete the focal point.
    if ($existing_focal_point) {
      if (!empty($focal_point)) {
        // The focal point has changed to a non-empty value.
        \Drupal::database()->merge('focal_point')
          ->key(array('fid' => $fid))
          ->fields(array('focal_point' => $focal_point))
          ->execute();
        self::flush($fid);
      }
      else {
        // The focal point has changed to an empty value.
        self::delete($fid);
      }
    }
    elseif (!empty($focal_point)) {
      // The focal point is both new and non-empty.
      \Drupal::database()->merge('focal_point')
        ->key(array('fid' => $fid))
        ->fields(array('focal_point' => $focal_point))
        ->execute();
    }

    // Clear the static caches.
    unset(self::$focal_point_values[$fid]);
    \Drupal\Core\Cache\Cache::invalidateTags(array($fid));
  }

  /**
   * Implements \Drupal\focal_point\FocalPoint::delete().
   *
   * Deletes the focal point values for the given file from the database.
   *
   * @param int $fid
   */
  public static function delete($fid) {
    self::flush($fid);
    db_delete('focal_point')
      ->condition('fid', $fid)
      ->execute();
  }

  /**
   * Implements \Drupal\focal_point\FocalPoint::parse().
   *
   * Return the given focal point value broken out into its component pieces as
   * an array in the following form:
   *   - x-offset: x value
   *   - y-offset: y value
   * If all else fails, return the parsed default focal point value.
   *
   * @param string $focal_point
   *
   * @return array
   */
  public static function parse($focal_point) {
    if (empty($focal_point) || !self::validate($focal_point)) {
      $focal_point = self::DEFAULT_VALUE;
    }

    return array_combine(array('x-offset', 'y-offset'), explode(',', $focal_point));
  }

  /**
   * Implements \Drupal\focal_point\FocalPoint::flush().
   *
   * Flush all image derivatives for the given file.
   *
   * @param int $fid
   */
  public static function flush($fid) {
    $file = file_load($fid);
    image_path_flush($file->getFileUri());
  }

  /**
   * Implements \Drupal\focal_point\FocalPoint::validate().
   *
   * Decides if the given focal point value is valid.
   *
   * @param string $focal_point
   *
   * @return bool
   */
  public static function validate($focal_point) {
    if (preg_match('/^(100|[0-9]{1,2})(,)(100|[0-9]{1,2})$/', $focal_point)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }
}
