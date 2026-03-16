<?php

namespace Sunnysideup\TemplateOverview\Api;

use Override;
use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Core\ClassInfo;

class ElementalDetails extends SiteTreeDetails
{
    #[Override]
    protected function getClassList(): array
    {
        if (class_exists(BaseElement::class)) {
            return ClassInfo::subclassesFor(BaseElement::class, false);
        }

        return [];
    }
}
