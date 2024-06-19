Template Overview Page
================================================

Provides the developer with a bunch of tools to
review the page types used on a website.

Make sure to check out the
`/dev/tasks/smoketest`
task as this will provide you with a very useful
implementation of the poorman's template checker.


Other useful links
----------------------------------------------
 - /admin/templates - list of all page types with examples
 - .admin/templates-elemental - elementals



Developer
-----------------------------------------------

Nicolaas Francken [at] sunnysideup.co.nz


Requirements
-----------------------------------------------

see composer.json
HIGHLY RECOMMENDED:
prettyphoto:
* http://sunny.svnrepository.com/svn/sunny-side-up-general/prettyPhoto OR
* https://github.com/sunnysideup/silverstripe-prettyphoto


Documentation
-----------------------------------------------

Please contact author for more details.

Any bug reports and/or feature requests will be
looked at in detail

We are also very happy to provide personalised support
for this module in exchange for a small donation.

This module allows you to review all your classes
(templates) used on a website.

It has a page type that lists all templates used on the website.

You can also include a footer on every page with information about the template being used.

When logged-in as admin, the TemplateDevelopment template include allows you to
visit the front-end of the website and:

1. upload and view designs
2. link through to edit page in the CMS
3. review stats (last changed, etc... for the template).


Installation Instructions
-----------------------------------------------

1. Find out how to add modules to SS and add module as per usual.

2. Review configs and add entries to app/_config/config.yml (or similar) as necessary.
	In the /_config/ folder of this module you can usually find
	some examples of config options (if any).

3. to any page template, add the following function
	To provide help instructions.

			function getHowToMakeThisTemplateWorkArray() {
				$a = [];
				//OR $a = parent::getHowToMakeThisTemplateWorkArray();
				$a[] = "Select header and three items for section one";
				$a[] = "Select header and three items for section two";
				return $a;
			}


4. [OPTIONAL] add to the bottom of your Page.ss file:

		<% include TemplateOverviewPageDevelopmentFooter %>


Isses with smoketest
---------------------------------------------------

If you try to run Smoketest, and all of your links are resulting in an HTTP
Response of 500 and a Response of
`unexpected response: Internal Server Error500|Internal Server Error`, you
probably need to add your IP address to the `allowed_ips` config for the module.
Check your logs and you should see a message like:

```
Got error 'PHP message: PHP Fatal error:  Please include your ip address in LoginAndRedirect.allowed_ips: 192.168.247.5.
Currently set are: 127.0.0.1 in /var/www/html/vendor/sunnysideup/templateoverview/src/Control/LoginAndRedirect.php on line 52'
```

Create a new YAML config file in `/app/_config` named `templateoverview.yml`, and add the follow content, replacing the IP address with the one mentioned in your error message:

```
---
Name: templateoverview_app
---
Sunnysideup\TemplateOverview\Control\LoginAndRedirect:
  allowed_ips:
    - 192.168.247.5
