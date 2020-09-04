<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use SilverStripe\Reports\Report;
use Sunnysideup\TemplateOverview\Api\AllLinksProviderBase;

class AllLinksReports extends AllLinksProviderBase
{
    public function getAllLinksInner(): array
    {
        $array = [];
        $reports = Report::get_reports();
        foreach ($reports as $report) {
            $link = $report->getLink();
            $array[$link] = $link;
        }

        return $array;
    }
}
