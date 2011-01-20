At its most basic this class will crop, scale, and slightly sharpen an image
for viewing it as a smaller thumbnail.

Optionally, one can override all options to do something like:

*	crop an image to the upper-left corner
*	scale the result to a specific pixel size
* post-process with sharpen, etc.

TODO:

*	Create a module for loading the image
	*	If local to the current machine, locate its full path and open.
	*	If remote, then download via curl.