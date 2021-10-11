<?php

namespace Sunnysideup\TemplateOverview\Api;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\ClassInfo;

class ElementalDetails extends SiteTreeDetails
{
    protected function getClassList()
    {
        return ClassInfo::subclassesFor(BaseElement::class, false);
    }
}
