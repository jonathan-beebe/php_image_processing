Released under the MIT license (see accompanying License.txt file).

Feel free to use as needed, and fork at will.

Image Processing
================

Given an image with a path on the sever like this:

    /site_content/cms_content/library/galleries/btg89/_0095165_1024.jpg.group/_0095165_1024.jpg

The url to a 98x square version would be (encoded):

    http://beebeography.com/image.php?i=/site_content%2Fcms_content%2Flibrary%2Fgalleries%2Fbtg89%2F_0095165_1024.jpg.group%2F_0095165_1024.jpg&w=98px&h=98px

An example url with the full path from root of site, in this case `/var/www`

    http://localhost/Sites/php/php_image_processing/image.php?i=/Sites/php/php_image_processing/test_images/boats.jpg&w=100&h=100

An example url with the path relative to the script

    http://localhost/Sites/php/php_image_processing/image.php?i=test_images/boats.jpg&w=100&h=100

