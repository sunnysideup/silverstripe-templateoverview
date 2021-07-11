<?php

namespace Sunnysideup\TemplateOverview\Control;

use PageController;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;
use Sunnysideup\PrettyPhoto\PrettyPhoto;
use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;
use Sunnysideup\TemplateOverview\Api\ElementalDetails;

use Sunnysideup\TemplateOverview\Control\TemplateOverviewPageController;

/**
 *@author: nicolaas [at] sunnysideup.co.nz
 *@description Add a page to your site that allows you to view all the html that can be used in the typography section - if applied correctly.
 */
class TemplateOverviewForElementalPageController extends TemplateOverviewPageController
{
    private static $url_segment = 'templateoverviewtemplates-elemental';

    private static $base_class = BaseElement::class;

    private static $base_class_provider = ElementalDetails::class;
}
