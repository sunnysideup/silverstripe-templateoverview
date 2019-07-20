<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Dev\BuildTask;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\ViewableData;

use Sunnysideup\TemplateOverview\Api\AllLinks;


class CheckAllTemplates extends BuildTask
{

    private static $segment = 'smoketest';

    /**
     *
     * @inheritdoc
     */
    protected $title = 'Check URLs for HTTP errors';

    /**
     *
     * @inheritdoc
     */
    protected $description = "Will go through main URLs (all page types (e.g Page, MyPageTemplate), all page types in CMS (e.g. edit Page, edit HomePage, new MyPage) and all models being edited in ModelAdmin, checking for HTTP response errors (e.g. 404). Click start to run.";


    /**
     * Main function
     * has two streams:
     * 1. check on url specified in GET variable.
     * 2. create a list of urls to check
     *
     * @param HTTPRequest
     */
    public function run($request)
    {
        ini_set('max_execution_time', 3000);


        //we have this check here so that even in dev mode you have to log in.
        //because if you do not log in, the test will not work.
        if (!Permission::check('ADMIN')) {
            return Security::permissionFailure();
        }

        $count = 0;

        $allLinks = Injector::inst()->get(AllLinks::class)->getAllLinks();

        $sections = ["allNonCMSLinks", "allCMSLinks"];
        $links = ArrayList::create();

        foreach ($sections as $isCMSLink => $sectionVariable) {
            foreach ($allLinks[$sectionVariable] as $link) {
                $count++;

                $links->push(ArrayData::create([
                    'IsCMSLink' => $isCMSLink,
                    'Link' => $link,
                    'ItemCount' => $count,
                ]));
            }
        }

        $otherLinks = "";
        $className = "";
        foreach ($allLinks['otherLinks'] as $linkArray) {
            if ($linkArray["ClassName"] != $className) {
                $className = $linkArray["ClassName"];
                $otherLinks .= "</ul><h2>".$className."</h2><ul>";
            }
            $otherLinks .= "<li><a href=\"" . $linkArray["Link"] . "\">" . $linkArray["Link"] . "</a></li>";
        }

        Requirements::javascript('https://code.jquery.com/jquery-3.3.1.min.js');
        Requirements::javascript('sunnysideup/templateoverview:client/javascript/checkalltemplates.js');
        Requirements::css('sunnysideup/templateoverview:client/css/checkalltemplates.css');

        $template = new SSViewer('CheckAllTemplates');

        print $template->process(
            ViewableData::create(),
            [
                'Title' => $this->title,
                'Links' => $links,
                'OtherLinks' => $otherLinks,
                'AbsoluteBaseURLMinusSlash' => trim(Director::absoluteBaseURL(), '/'),
            ]
        );
    }
}
