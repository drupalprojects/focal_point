//@ sourceURL=focal_point.js

/**
 * @file
 * Javascript functionality for the focal point widget.
 */

(function($, Drupal) {
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
        var $field = $(".focal-point-" + $(this).attr('data-field-name') + '-' + $(this).attr('data-delta'));
        var fp = new Drupal.FocalPoint($indicator, $img, $field);

        // Set the position of the indicator on image load and any time the
        // field value changes. We use a bit of hackery to make certain that the
        // image is loaded before moving the crosshair. See http://goo.gl/B02vFO
        // The setTimeout was added to ensure the focal point is set properly on
        // modal windows. See http://goo.gl/s73ge.
        setTimeout(function() {
          $img.one('load', function(){
            fp.setIndicator();
          }).each(function() {
            if (this.complete) $(this).load();
          });
        }, 0);

      });
    }

  };

  /**
   * Object representing the focal point for a given image.
   *
   * @param object $indicator
   *   The indicator jQuery object whose position should be set.
   * @param object $img
   *   The image jQuery object to which the indicator is attached.
   * @param array $field
   *   The field jQuery object where the position can be found.
   */
  Drupal.FocalPoint = function($indicator, $img, $field) {
    this.$indicator = $indicator;
    this.$img = $img;
    this.$field = $field;

    // Make the focal point indicator draggable and tell it to update the
    // appropriate field when it is moved by the user.
    this.$indicator.draggable({
      containment: this.$img,
      stop: function() {
        var imgOffset = this.$img.offset();
        var focalPointOffset = this.$indicator.offset();

        var leftDelta = focalPointOffset.left - imgOffset.left;
        var topDelta = focalPointOffset.top - imgOffset.top;

        this.set(leftDelta, topDelta);
      }
    });

    // Allow users to double-click the indicator to reveal the focal point form
    // element.
    this.$indicator.dblclick(function() {
      this.$field.closest('.form-item').toggle();
    });

    // Allow users to click on the image preview in order to set the focal_point
    // and set a cursor.
    this.$img.click(event, function() {
      this.set(event.offsetX, event.offsetY);
    });
    this.$img.css('cursor', 'crosshair');

    // Add a change event to the focal point field so it will properly update
    // the indicator position.
    this.$field.change(function() {
      // Update the indicator position in case someone has typed in a value.
      this.setIndicator();
    });


  }

  /**
   * Set the focal point.
   *
   * @param int offsetX
   *   Left offset in pixels.
   * @param int offsetY
   *   Top offset in pixels.
   */
  Drupal.FocalPoint.prototype.set = function(offsetX, offsetY) {
    var focalPoint = this.calculate(offsetX, offsetY);
    this.$field.val(focalPoint.x + ',' + focalPoint.y).trigger('change');
    this.setIndicator();
  }

  /**
   * Change the position of the focal point indicator. This may not work in IE7.
   */
  Drupal.FocalPoint.prototype.setIndicator = function() {
    var coordinates = this.$field.val() !== '' && this.$field.val() !== undefined ? this.$field.val().split(',') : [50,50];
    this.$indicator.css('left', (parseInt(coordinates[0], 10) / 100) * this.$img.width());
    this.$indicator.css('top', (parseInt(coordinates[1], 10) / 100) * this.$img.height());
    this.$field.val(coordinates[0] + ',' + coordinates[1]);
  }

  /**
   * Calculate the focal point for the given image.
   *
   * @param int offsetX
   *   Left offset in pixels.
   * @param int offsetY
   *   Top offset in pixels.
   *
   * @returns object
   */
  Drupal.FocalPoint.prototype.calculate = function(offsetX, offsetY) {
    var focalPoint = {};
    focalPoint.x = this.round(100 * offsetX / this.$img.width(), 0, 100);
    focalPoint.y = this.round(100 * offsetY / this.$img.height(), 0, 100);

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
  Drupal.FocalPoint.prototype.round = function(value, min, max){
    var roundedVal = Math.max(Math.round(value), min);
    roundedVal = Math.min(roundedVal, max);

    return roundedVal;
  }

})(jQuery, Drupal);
