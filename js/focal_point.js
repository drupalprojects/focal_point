//@ sourceURL=focal_point.js

/**
 * @file
 * Javascript functionality for the focal point widget.
 */

(function($) {

  /**
   * Focal Point indicator.
   */
  Drupal.behaviors.focalPointIndicator = {
    attach: function(context, settings) {
      $(".focal-point-indicator", context).once(function(){
        // Set some variables for the different pieces at play.
        var $indicator = $(this);
        var delta = $(this).attr('data-delta');
        var $img = $(this).siblings('img');
        var $field = $(".focal-point-" + delta);

        // Hide the focal_point form item. We do this with js so that a non-js
        // user can still set the focal point values. Also, add functionality so
        // that if the indicator is double clicked, the form item is displayed.
        $field.closest('.form-item').hide();
        $indicator.dblclick(function() {
          $field.closest('.form-item').toggle();
        });

        // If the focal point value is already set, move the indicator to that
        // location. Otherwise center it. Make sure the img has loaded first.
        // This method will not work in IE7. Oh well.
        $img.load(function() {
	  var coordinates = $field.val() !== '' && $field.val() !== undefined ? $field.val().split(',') : [50,50];
          $indicator.css('left', (parseInt(coordinates[0], 10) / 100) * $(this).width());
          $indicator.css('top', (parseInt(coordinates[1], 10) / 100) * $(this).height());
          $field.val(coordinates[0] + ',' + coordinates[1]);
        });

        // Make the focal point indicator draggable and tell it to update the
        // appropriate field when it is moved by the user.
        $(this).draggable({
          containment: $img,
          stop: function() {
            var imgOffset = $img.offset();
            var focalPointOffset = $(this).offset();

            var leftDelta = focalPointOffset.left - imgOffset.left;
            var topDelta = focalPointOffset.top - imgOffset.top;

            var leftOffset = focalPointRound(100 * leftDelta / $img.width(), 0, 100, 100);
            var topOffset = focalPointRound(100 * topDelta / $img.height(), 0, 100, 100);

            $field.val(leftOffset + ',' + topOffset);
          }
        });

      });
    }

  };

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

