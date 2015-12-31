<?php

/**
 *@author nicolaas[at]sunnysideup.co.nz
 *@description: adds functionality to controller for dev purposes only
 *
 **/


class TemplateOverviewPageExtension extends Extension
{

    protected $templateList = null;

    public function TemplateOverviewPage()
    {
        return TemplateOverviewPage::get()->First();
    }

    public function IncludeTemplateOverviewDevelopmentFooter()
    {
        if (Director::isDev()) {
            Requirements::javascript("templateoverview/javascript/TemplateOverviewExtension.js");
            Requirements::themedCSS("TemplateOverviewExtension", "templateoverview");
            return true;
        }
        return false;
    }

    public function NextTemplateOverviewPage()
    {
        $list = $this->TemplateList();
        $doIt = false;
        if ($list) {
            foreach ($list as $page) {
                if ($doIt) {
                    return $page;
                }
                if ($page->ClassName == $this->owner->ClassName) {
                    $doIt = true;
                }
            }
        }
    }

    public function PrevTemplateOverviewPage()
    {
        $list = $this->TemplateList();
        $doIt = false;
        if ($list) {
            foreach ($list as $page) {
                if ($page->ClassName == $this->owner->ClassName) {
                    $doIt = true;
                }
                if ($doIt && isset($previousPage)) {
                    return $previousPage;
                }
                $previousPage = $page;
            }
        }
    }

    public function TemplateList()
    {
        if (!$this->templateList) {
            $page = TemplateOverviewPage::get()->First();
            if ($page) {
                $this->templateList = $page->ListOfAllClasses();
            }
        }
        return $this->templateList;
    }

    public function TemplateDescriptionForThisClass()
    {
        return TemplateOverviewDescription::get()
            ->filter(array("ClassNameLink" => $this->owner->ClassName))
            ->First();
    }
}
