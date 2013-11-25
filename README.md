Focal Point allows you to specify the portion of an image that is most
important. This information can be used when the image is cropped or cropped and
scaled so that you don't, for example, end up with an image that cuts off the
subject's head.

This module borrows heavily from the ImageField Focus module but it works in a
fundamentally different way. In this module the focus is defined as a single
point on the image. Among other things this helps to solve the problem of
guaranteeing the size of a cropped image as described here:
https://drupal.org/node/1889542.

Additionally, Focal Point integrates both with standard image fields as well as
media fields provided by the media module.

There is an update path provided (during installation) that will migrate
existing imagefield_focus data to focal_points. 
