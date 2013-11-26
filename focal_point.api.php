<?php

/**
 * @file
 * Documentation of Feeds hooks.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter an array of supported widget types.
 *
 * @param array $supported
 */
function hook_focal_point_supported_widget_types_alter(&$supported) {
  $supported[] = 'mymodule_my_custom_widget_type';
}

/**
 * Alter an array of supported file entity types.
 *
 * @param array $supported
 */
function hook_focal_point_supported_file_types_alter(&$supported) {
  $supported[] = 'custom_file_entity_type';
}

/**
 * @} End of "addtogroup hooks".
 */
