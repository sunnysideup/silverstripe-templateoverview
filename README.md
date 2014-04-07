Template Overview Page================================================================================

Provides the developer with a bunch of tools to
review the page types used on a website.

Make sure to check out the
/dev/tasks/CheckAllTemplates
task as this will provide you with a very useful
implementation of the poorman's template checker.


Developer
-----------------------------------------------
Nicolaas Francken [at] sunnysideup.co.nz


Requirements
-----------------------------------------------
see composer.json
HIGHLY RECOMMENDED:
prettyphoto:
- http://sunny.svnrepository.com/svn/sunny-side-up-general/prettyPhoto OR
- https://github.com/sunnysideup/silverstripe-prettyphoto


Documentation
-----------------------------------------------
Please contact author for more details.

Any bug reports and/or feature requests will be
looked at in detail

We are also very happy to provide personalised support
for this module in exchange for a small donation.

This module allows you to review all your classes
(templates) used on a website.

It has a page type that lists all templates used on the website (with links and icons).

It also allows you to add:

<% include TemplateOverviewPageDevelopmentFooter %> at the bottom of your Page.ss file.

When logged-in as admin, the TemplateDevelopment template include allows you to
visit the front-end of the website and:

a. upload and view designs
b. view and edit to do field (as per SS standard install)
c. add a testing entry:
	- it will automatically note browser operating system etc... you are using
	- date
	- notes (i.e. tested on ie6 by so and so on 12/12/2012)
d. link through to edit page in the CMS
e. review stats (last changed, etc... for the template).

These fields are added as Many_Many (image object and testing object).
The reason for this is to minimise the footprint on the SiteTree table.


Installation Instructions
-----------------------------------------------
1. Find out how to add modules to SS and add module as per usual.

2. Review configs and add entries to mysite/_config/config.yml
(or similar) as necessary.
In the _config/ folder of this module
you can usually find some examples of config options (if any).

3. to any page template, add the following function:

	function getHowToMakeThisTemplateWorkArray() {
		$a = array();
		//OR $a = parent::getHowToMakeThisTemplateWorkArray();
		$a[] = "Select header and three items for section one";
		$a[] = "Select header and three items for section two";
		return $a;
	}

To provide help instructions.



Adding Overlays
-----------------------------------------------
If you create a file:

TemplateOverviewOverlay.js in your project folder
e.g.

mysite/javascript/TemplateOverviewOverlay.js

using TemplateOverviewOverlayExample.js as the example
then you can create useful overlays.  These overlays
can show the design graphic over your own design, allowing
visual checks of your css work.
