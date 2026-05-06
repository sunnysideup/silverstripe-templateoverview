<?php

namespace Sunnysideup\TemplateOverview\Api;

use SilverStripe\Admin\CMSMenu;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksArchiveAdmin;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksControllerInfo;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksDataObjects;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksModelAdmin;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksPages;
use Sunnysideup\TemplateOverview\Api\Providers\AllLinksReports;

class AllLinks extends AllLinksProviderBase
{
    /**
     * @var mixed|mixed[]
     */
    public $archiveCMSLinks;

    /**
     * @var array
     */
    protected $allNonCMSLinks = [];

    /**
     * @var array
     */
    protected $pagesOnFrontEnd = [];

    /**
     * @var array
     */
    protected $dataObjectsOnFrontEnd = [];

    /**
     * @var array
     */
    protected $otherControllerMethods = [];

    /**
     * @var array
     */
    protected $customNonCMSLinks = [];

    /**
     * @var array
     */
    protected $templateoverviewtestsLinks = [];

    /**
     * @var array
     */
    protected $allCMSLinks = [];

    /**
     * @var array
     */
    protected $pagesInCMS = [];

    /**
     * @var array
     */
    protected $dataObjectsInCMS = [];

    /**
     * @var array
     */
    protected $modelAdmins = [];

    /**
     * @var array
     */
    protected $reportLinks = [];

    /**
     * @var array
     */
    protected $leftAndMainLnks = [];

    /**
     * @var array
     */
    protected $customCMSLinks = [];

    public static function is_admin_link(string $link): bool
    {
        return str_starts_with(ltrim($link, '/'), 'admin');
    }


    /**
     * returns an array of allNonCMSLinks => [] , allCMSLinks => [], otherControllerMethods => [].
     */
    public function getAllLinks(): array
    {
        if ($this->numberOfExamples === 0) {
            $this->numberOfExamples = $this->Config()->number_of_examples;
        }

        if ($this->includeFrontEnd) {
            $array1 = $this->config()->get('custom_links');
            $array2 = $this->getCustomisedLinks();
            foreach (array_merge($array1, $array2) as $link) {
                $link = '/' . ltrim((string) $link, '/') . '/';
                if (self::is_admin_link($link)) {
                    $this->customCMSLinks[] = $link;
                } else {
                    $this->customNonCMSLinks[] = $link;
                }
            }

            $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->customNonCMSLinks);
            $this->pagesOnFrontEnd = $this->ListOfPagesLinks();
            $this->dataObjectsOnFrontEnd = $this->ListOfDataObjectsLinks(false);

            $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->pagesOnFrontEnd);
            $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->dataObjectsOnFrontEnd);
        }

        sort($this->allNonCMSLinks);

        if ($this->includeBackEnd) {
            $this->templateoverviewtestsLinks = $this->ListOfAllTemplateoverviewtestsLinks();
            $this->pagesInCMS = $this->ListOfPagesLinks(true);
            $this->dataObjectsInCMS = $this->ListOfDataObjectsLinks(true);
            $this->modelAdmins = $this->ListOfAllModelAdmins();
            $this->archiveCMSLinks = $this->ListOfAllArchiveCMSLinks();
            $this->leftAndMainLnks = $this->ListOfAllLeftAndMains();
            $this->reportLinks = $this->listOfAllReports();

            $this->allNonCMSLinks = $this->addToArrayOfLinks($this->allNonCMSLinks, $this->templateoverviewtestsLinks);
            $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->pagesInCMS);
            $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->dataObjectsInCMS);
            $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->modelAdmins);
            $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->archiveCMSLinks);
            $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->leftAndMainLnks);
            $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->reportLinks);
            $this->allCMSLinks = $this->addToArrayOfLinks($this->allCMSLinks, $this->customCMSLinks);
            sort($this->allCMSLinks);
            $this->otherControllerMethods = $this->ListOfAllControllerMethods();
        }

        return [
            'allNonCMSLinks' => $this->allNonCMSLinks,
            'allCMSLinks' => $this->allCMSLinks,
            'otherLinks' => $this->otherControllerMethods,
        ];
    }

    /**
     * returns a list of all model admin links.
     *
     * @return array
     */
    public function ListOfAllModelAdmins()
    {
        $obj = Injector::inst()->get(AllLinksModelAdmin::class);
        $obj->setNumberOfExamples($this->getNumberOfExamples());

        return $obj->getAllLinksInner();
    }

    /**
     * returns a list of all archive links.
     *
     * @return array
     */
    public function ListOfAllArchiveCMSLinks()
    {
        $obj = Injector::inst()->get(AllLinksArchiveAdmin::class);
        $obj->setNumberOfExamples($this->getNumberOfExamples());

        return $obj->getAllLinksInner();
    }

    public function getCustomisedLinks(): array
    {
        $obj = Injector::inst()->get(AllLinksControllerInfo::class);
        $obj->setNumberOfExamples($this->getNumberOfExamples());

        return $obj->getCustomisedLinks();
    }

    public function ListOfAllControllerMethods(): array
    {
        $obj = Injector::inst()->get(AllLinksControllerInfo::class);
        $obj->setNumberOfExamples($this->getNumberOfExamples());
        $obj->setValidNameSpaces($this->config()->controller_name_space_filter);

        return $obj->getAllLinksInner();
    }

    public function ListOfAllTemplateoverviewtestsLinks(): array
    {
        $obj = Injector::inst()->get(AllLinksControllerInfo::class);
        $obj->setNumberOfExamples($this->getNumberOfExamples());

        $list = $obj->getAllLinksInner();
        $list = $obj->getLinksAndActions();

        return array_keys($list['CustomLinks'] ?? []);
    }

    public function ListOfDataObjectsLinks(bool $inCMS): array
    {
        $obj = Injector::inst()->get(AllLinksDataObjects::class);
        $obj->setNumberOfExamples($this->getNumberOfExamples());

        return $obj->getAllLinksInner($inCMS);
    }

    /**
     * Takes {@link #$classNames}, gets the URL of the first instance of it
     * (will exclude extensions of the class) and
     * appends to the {@link #$urls} list to be checked.
     *
     * @param bool $pageInCMS
     *
     * @return array
     */
    public function ListOfPagesLinks($pageInCMS = false)
    {
        $obj = Injector::inst()->get(AllLinksPages::class);
        $obj->setNumberOfExamples($this->getNumberOfExamples());

        return $obj->getAllLinksInner($pageInCMS);
    }

    /**
     * @return array
     */
    public function ListOfAllLeftAndMains()
    {
        //first() will return null or the object
        $return = [];
        $list = CMSMenu::get_cms_classes();
        foreach ($list as $class) {
            if ($this->isValidClass($class)) {
                $obj = Injector::inst()->get($class);
                if ($obj) {
                    $url = $obj->Link();
                    if ($url) {
                        $return[] = $url;
                    }
                }
            }
        }

        return $return;
    }

    /**
     * returns a list of all reports.
     *
     * @return array
     */
    public function ListOfAllReports()
    {
        $reportsLinks = Injector::inst()->get(AllLinksReports::class);

        return $reportsLinks->getAllLinksInner();
    }


}
