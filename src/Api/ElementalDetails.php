<?php

namespace Sunnysideup\TemplateOverview\Api;

use SilverStripe\Core\ClassInfo;

class ElementalDetails extends SiteTreeDetails
{
    protected function getClassList(): array
    {
        if (class_exists('DNADesign\\Elemental\\Models\\BaseElement')) {
            return ClassInfo::subclassesFor('DNADesign\\Elemental\\Models\\BaseElement', false);
        }

        return [];
    }
}
