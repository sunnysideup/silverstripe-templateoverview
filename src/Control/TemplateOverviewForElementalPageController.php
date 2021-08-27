<?php

namespace Sunnysideup\TemplateOverview\Control;

use DNADesign\Elemental\Models\BaseElement;
use Sunnysideup\TemplateOverview\Api\ElementalDetails;

/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */
class TemplateOverviewForElementalPageController extends TemplateOverviewPageController
{
    private static $url_segment = 'admin/templateoverviewtemplates-elemental';

    private static $base_class = BaseElement::class;

    private static $base_class_provider = ElementalDetails::class;
}
