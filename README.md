###############################################
TemplateOverviewPage
Pre 0.1 proof of concept
###############################################

Developer
-----------------------------------------------
Nicolaas Francken [at] sunnysideup.co.nz

Requirements
-----------------------------------------------
SilverStripe 2.3.0 or greater.
HIGHLY RECOMMENDED:
prettyphoto: http://sunny.svnrepository.com/svn/sunny-side-up-general/prettyPhoto

Documentation
-----------------------------------------------
this module allows you to review all your classes
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
2. copy configurations from this module's _config.php file
into mysite/_config.php file and edit settings as required.
NB. the idea is not to edit the module at all, but instead customise
it from your mysite folder, so that you can upgrade the module without redoing the settings.
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
