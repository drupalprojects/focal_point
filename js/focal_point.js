//@ sourceURL=focal_point.js

/**
 * @file
 * Javascript functionality for the focal point widget.
 */

(function($) {
  'use strict';

  /**
   * Focal Point indicator.
   */
  Drupal.behaviors.focalPointIndicator = {
    attach: function(context, settings) {
      $(".focal-point", context).once(function() {
        // Hide the focal_point form item. We do this with js so that a non-js
        // user can still set the focal point values. Also, add functionality so
        // that if the indicator is double clicked, the form item is displayed.
        if (!$(this).hasClass('error')) {
          $(this).closest('.form-item').hide();
        }
      });

      $(".focal-point-indicator", context).once(function() {
        // Set some variables for the different pieces at play.
        var $indicator = $(this);
        var $img = $(this).siblings('img');
        var $fieldDelta = $(this).attr('data-delta');
        var $fieldName = $(context).find('#file-entity-add-upload').length > 0 ? 'media' : $(this).attr('data-field-name');
        var $field = $(".focal-point-" + $fieldName + '-' + $fieldDelta);
        var $previewLink = $(".focal-point-preview-link-" + $fieldName + '-' + $fieldDelta);

        $indicator.dblclick(function() {
          $field.closest('.form-item').toggle();
        });

        $img.css('cursor', 'crosshair');

        // Set the position of the indicator on image load and any time the
        // field value changes. We use a bit of hackery to make certain that the
        // image is loaded before moving the crosshair. See http://goo.gl/B02vFO
        // The setTimeout was added to ensure the focal point is set properly on
        // modal windows. See http://goo.gl/s73ge.
        setTimeout(function() {
          $img.one('load', function(){
            focalPointSetIndicator($indicator, $(this), $field);
          }).each(function() {
            if (this.complete) $(this).load();
          });
        }, 0);

        // Make the focal point indicator draggable and tell it to update the
        // appropriate field when it is moved by the user.
        $(this).draggable({
          containment: $img,
          stop: function() {
            var imgOffset = $img.offset();
            var focalPointOffset = $(this).offset();

            var leftDelta = focalPointOffset.left - imgOffset.left;
            var topDelta = focalPointOffset.top - imgOffset.top;

            focalPointSet(leftDelta, topDelta, $img, $indicator, $img);
          }
        });

        // Allow users to click on the image preview in order to set the focal_point.
        $img.click(event, function() {
          focalPointSet(event.offsetX, event.offsetY, $img, $indicator, $img);
        });

        // Add a change event to the focal point field so it will properly
        // update the indicator position and the preview link.
        $field.change(function() {
          // Update the indicator position in case someone has typed in a value.
          focalPointSetIndicator($indicator, $img, $(this));

          // Re-jigger the href of the preview link.
          if ($previewLink.length > 0) {
            var href = $previewLink.attr('href').split('/');
            href.pop();
            href.push(encodeURIComponent($(this).val()));
            $previewLink.attr('href', href.join('/'));
          }
        });

      });
    }

  };

  /**
   * Set the focal point.
   *
   * @param int offsetX
   *   Left offset in pixels.
   * @param int offsetY
   *   Top offset in pixels.
   * @param object $img
   *   The image jQuery object to which the indicator is attached.
   * @param object $indicator
   *   The indicator jQuery object whose position should be set.
   * @param object $field
   *   The field jQuery object where the position can be found.
   */
  function focalPointSet(offsetX, offsetY, $img, $indicator, $field) {
    var focalPoint = focalPointCalculate(offsetX, offsetY, $img);
    $field.val(focalPoint.x + ',' + focalPoint.y).trigger('change');
    focalPointSetIndicator($indicator, $img, $field);
  }

  /**
   * Change the position of the focal point indicator. This may not work in IE7.
   *
   * @param object $indicator
   *   The indicator jQuery object whose position should be set.
   * @param object $img
   *   The image jQuery object to which the indicator is attached.
   * @param array $field
   *   The field jQuery object where the position can be found.
   */
  function focalPointSetIndicator($indicator, $img, $field) {
    var coordinates = $field.val() !== '' && $field.val() !== undefined ? $field.val().split(',') : [50,50];
    $indicator.css('left', (parseInt(coordinates[0], 10) / 100) * $img.width());
    $indicator.css('top', (parseInt(coordinates[1], 10) / 100) * $img.height());
    $field.val(coordinates[0] + ',' + coordinates[1]);
  }

  /**
   * Calculate the focal point for the given image.
   *
   * @param int offsetX
   *   Left offset in pixels.
   * @param int offsetY
   *   Top offset in pixels.
   * @param object $img
   *   The image jQuery object to which the indicator is attached.
   *
   * @returns object
   */
  function focalPointCalculate(offsetX, offsetY, $img) {
    var focalPoint = {};
    focalPoint.x = focalPointRound(100 * offsetX / $img.width(), 0, 100);
    focalPoint.y = focalPointRound(100 * offsetY / $img.height(), 0, 100);

    return focalPoint;
  }

  /**
   * Rounds the given value to the nearest integer within the given bounds.
   *
   * @param float value
   *   The value to round.
   * @param int min
   *   The lower bound.
   * @param max
   *   The upper bound.
   *
   * @returns int
   */
  function focalPointRound(value, min, max){
    var roundedVal = Math.max(Math.round(value), min);
    roundedVal = Math.min(roundedVal, max);

    return roundedVal;
  }

})(jQuery);
