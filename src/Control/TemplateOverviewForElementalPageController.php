<?php

namespace Sunnysideup\TemplateOverview\Control;

use Sunnysideup\TemplateOverview\Api\ElementalDetails;

/**
 * Class \Sunnysideup\TemplateOverview\Control\TemplateOverviewForElementalPageController
 *
 */
class TemplateOverviewForElementalPageController extends TemplateOverviewPageController
{
    private static $url_segment = 'admin/templates-elemental';

    private static $base_class = '\\DNADesign\\Elemental\\Models\\BaseElement';

    private static $base_class_provider = ElementalDetails::class;

    public function IsElemental(): bool
    {
        return true;
    }
}
