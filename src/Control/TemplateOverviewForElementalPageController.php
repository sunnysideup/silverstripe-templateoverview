<?php

namespace Sunnysideup\TemplateOverview\Control;

use Sunnysideup\TemplateOverview\Api\ElementalDetails;

/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */
class TemplateOverviewForElementalPageController extends TemplateOverviewPageController
{
    private static $url_segment = 'admin/templates-elemental';

    private static $base_class = '\\DNADesign\\Elemental\\Models\\BaseElement';

    private static $base_class_provider = ElementalDetails::class;
}
