<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Permission;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ViewableData;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Control\CheckAllTemplatesResponseController;

class CheckAllTemplatesCli extends BuildTask
{
    protected $title = 'CLI ONLY: Check URLs for HTTP errors';

    protected $description = 'Run this task from the command line to check for HTTP response errors (e.g. 404).';

    private static $segment = 'smoketestcli';

    /**
     * Main function
     * has two streams:
     * 1. check on url specified in GET variable.
     * 2. create a list of urls to check.
     *
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {

        //we have this check here so that even in dev mode you have to log in.
        //because if you do not log in, the test will not work.
        if (! Director::is_cli()) {
            die('Only run from CLI');
        }
        $obj = Injector::inst()->get(AllLinks::class);

        $allLinks = $obj->getAllLinks();
        $controller = new CheckAllTemplatesResponseController();
        foreach($allLinks['allNonCMSLinks'] as $link) {
            $testLink = $this->createTestLink($link, false);
            print_r($controller->testOneInner($testLink, false));

        }
        foreach($allLinks['allCMSLinks'] as $link) {
            $testLink = $this->createTestLink($link, true);
            print_r($controller->testOneInner($testLink, false));
        }

    }

    protected function createTestLink(string $link, bool $isCmsLink = false)
    {
        return $this->baseURL() . $link;
    }

    protected function baseURL()
    {
        return rtrim(Director::absoluteBaseURL(), '/');
    }

}
