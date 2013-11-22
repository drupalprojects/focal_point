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
      $(".focal-point-indicator", context).each(function(){
        // Set some variables for the different pieces at play.
        var delta = $(this).attr('data-delta');
        var $img = $(this).siblings('img');
        var $field = $(".focal-point-" + delta);

        // Hide the focal_point form item. We do this with js so that a non-js
        // user can still set the focal point values.
        $field.closest('.form-item').hide();

        // If the focal point value is already set, move the indicator to that
        // location. Otherwise center it.
        var coordinates = $field.val() !== '' ? $field.val().split(',') : [50,50];
        $(this).css('left', (parseInt(coordinates[0], 10) / 100) * $img.width());
        $(this).css('top', (parseInt(coordinates[1], 10) / 100) * $img.height());


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

