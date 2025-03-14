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

class CheckAllTemplates extends BuildTask
{
    protected $title = 'SMOKETEST: Check URLs for HTTP errors';

    protected $description = 'Will go through main URLs (all page types (e.g Page, MyPageTemplate), all page types in CMS (e.g. edit Page, edit HomePage, new MyPage) and all models being edited in ModelAdmin, checking for HTTP response errors (e.g. 404). Click start to run.';

    private static $segment = 'smoketest';

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
        ini_set('max_execution_time', 3000);

        //we have this check here so that even in dev mode you have to log in.
        //because if you do not log in, the test will not work.
        if (! Permission::check('ADMIN')) {
            die('Please <a href="/Security/login/?BackURL=/dev/tasks/smoketest/">log in</a> first.');
        }
        $obj = Injector::inst()->get(AllLinks::class);

        if (! empty($_GET['limit'])) {
            $obj->setNumberOfExamples((int) $_GET['limit']);
        }

        if (! empty($_GET['nofrontend'])) {
            $obj->setIncludeFrontEnd(false);
        }

        if (! empty($_GET['nobackend'])) {
            $obj->setIncludeBackEnd(false);
        }

        $allLinks = $obj->getAllLinks();

        if (! empty($_GET['htmllist'])) {
            $this->htmlListOutput($allLinks);

            return;
        }
        if (! empty($_GET['sitemaperrors'])) {
            $this->sitemapErrorsOutput($obj);

            return;
        }

        $this->defaultOutput($allLinks);
    }

    protected function defaultOutput(array $allLinks)
    {
        $count = 0;
        $sections = ['allNonCMSLinks', 'allCMSLinks'];
        $links = ArrayList::create();

        foreach ($sections as $isCMSLink => $sectionVariable) {
            foreach ($allLinks[$sectionVariable] as $link) {
                ++$count;

                $links->push(ArrayData::create([
                    'IsCMSLink' => $isCMSLink,
                    'Link' => $link,
                    'ItemCount' => $count,
                ]));
            }
        }

        $otherLinks = '';
        $className = '';
        foreach ($allLinks['otherLinks'] as $linkArray) {
            if ($linkArray['ClassName'] !== $className) {
                $className = $linkArray['ClassName'];
                $otherLinks .= '</ul><h2>' . $className . '</h2><ul>';
            }

            $otherLinks .= '<li><a href="' . $linkArray['Link'] . '">' . $linkArray['Link'] . '</a></li>';
        }

        Requirements::javascript('sunnysideup/templateoverview:client/javascript/checkalltemplates.js');
        Requirements::themedCSS('client/css/checkalltemplates');

        $template = new SSViewer('CheckAllTemplates');

        echo $template->process(
            ViewableData::create(),
            [
                'Title' => $this->title,
                'Links' => $links,
                'OtherLinks' => $otherLinks,
                'AbsoluteBaseURLMinusSlash' => $this->baseURL(),
                'HasEnvironmentVariable' => ((bool) Environment::getEnv('SS_ALLOW_SMOKE_TEST')),
            ]
        );
    }

    protected function baseURL()
    {
        return rtrim(Director::absoluteBaseURL(), '/');
    }

    protected function htmlListOutput(array $allLinks)
    {
        $base = $this->baseURL();
        $array = [];
        foreach ($allLinks as $key => $list) {
            foreach ($list as $item) {
                if ('otherLinks' === $key) {
                    $link = $base . $item['Link'];
                    $title = $base . $item['Link'];
                } else {
                    $link = $base . $item;
                    $title = $base . $item;
                }

                $array[$link] = '<a href="' . $link . '">' . $title . '</a>';
            }
        }

        if ([] !== $array) {
            ksort($array);
            echo '<ol><li>' . implode('</li><li>', $array) . '</li></ol>';
        } else {
            echo 'No links available';
        }
    }

    protected function sitemapErrorsOutput($obj)
    {
        $this->baseURL();
        $array = $obj->getErrorsInGoogleSitemap();
        if (count($array) > 0) {
            foreach ($array as $error) {
                echo '<li>' . $error . '</li>';
            }
        } else {
            echo 'No errors found';
        }
    }
}
