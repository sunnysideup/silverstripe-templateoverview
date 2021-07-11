<?php

namespace Sunnysideup\TemplateOverview\Api;

use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Model\VirtualPage;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\View\ArrayData;

use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;

use DNADesign\Elemental\Models\BaseElement;

class ElementalDetails extends SiteTreeDetails
{


    protected function getClassList()
    {
        return ClassInfo::subclassesFor(BaseElement::class, false);
    }

}
