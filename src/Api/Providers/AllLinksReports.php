<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use SilverStripe\Admin\CMSMenu;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Reports\Report;

class AllLinksReports extends AllLinksProviderBase
{


    public function getAllLinksInner(): array
    {
        $array = [];
        $reports = Report::get_reports();
        foreach($reports as $report){
            $link = $report->getLink();
            $array [$link] = $link;
        }

        return $array;
    }
}
