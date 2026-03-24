<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\ModelData;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Permission;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class CheckAllTemplates extends BuildTask
{
    protected static string $commandName = 'smoketest';

    protected string $title = 'SMOKETEST: Check URLs for HTTP errors';

    protected static string $description = 'Will go through main URLs (all page types (e.g Page, MyPageTemplate), all page types in CMS (e.g. edit Page, edit HomePage, new MyPage) and all models being edited in ModelAdmin, checking for HTTP response errors (e.g. 404). Click start to run.';

    public function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                new InputOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit number of examples'),
                new InputOption('nofrontend', 'f', InputOption::VALUE_NONE, 'Exclude frontend links'),
                new InputOption('nobackend', 'b', InputOption::VALUE_NONE, 'Exclude backend links'),
                new InputOption('htmllist', 't', InputOption::VALUE_NONE, 'Render HTML list output'),
                new InputOption('sitemaperrors', 's', InputOption::VALUE_NONE, 'Show sitemap errors'),
            ]
        );
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        ini_set('max_execution_time', 3000);

        if (!Permission::check('ADMIN')) {
            $output->writeln('Please log in as an administrator first.');
            return Command::FAILURE;
        }

        $obj = Injector::inst()->get(AllLinks::class);

        $limit = $input->getOption('limit');
        if ($limit !== null) {
            $obj->setNumberOfExamples((int) $limit);
        }

        if ($input->getOption('nofrontend')) {
            $obj->setIncludeFrontEnd(false);
        }

        if ($input->getOption('nobackend')) {
            $obj->setIncludeBackEnd(false);
        }

        $allLinks = $obj->getAllLinks();

        if ($input->getOption('htmllist')) {
            $this->htmlListOutput($allLinks, $output);
            return Command::SUCCESS;
        }

        if ($input->getOption('sitemaperrors')) {
            $this->sitemapErrorsOutput($obj, $output);
            return Command::SUCCESS;
        }

        $this->defaultOutput($allLinks, $output);

        return Command::SUCCESS;
    }

    protected function defaultOutput(array $allLinks, PolyOutput $output)
    {
        $count = 0;
        $sections = ['allNonCMSLinks', 'allCMSLinks'];
        $links = ArrayList::create();

        foreach ($sections as $isCMSLink => $sectionVariable) {
            foreach ($allLinks[$sectionVariable] as $link) {
                ++$count;

                $links->push(ArrayData::create([
                    'IsCMSLink' => $isCMSLink,
                    'Link' => $link,
                    'ItemCount' => $count,
                ]));
            }
        }

        $otherLinks = '';
        $className = '';
        foreach ($allLinks['otherLinks'] as $linkArray) {
            if ($linkArray['ClassName'] !== $className) {
                $className = $linkArray['ClassName'];
                $otherLinks .= '</ul><h2>' . $className . '</h2><ul>';
            }

            $otherLinks .= '<li><a href="' . $linkArray['Link'] . '">' . $linkArray['Link'] . '</a></li>';
        }

        Requirements::javascript('sunnysideup/templateoverview:client/javascript/checkalltemplates.js');
        Requirements::themedCSS('client/css/checkalltemplates');

        $template = SSViewer::create('CheckAllTemplates');

        $output->writeln(
            $template->process(
                ModelData::create(),
                [
                    'Title' => $this->title,
                    'Links' => $links,
                    'OtherLinks' => $otherLinks,
                    'AbsoluteBaseURLMinusSlash' => $this->baseURL(),
                    'HasEnvironmentVariable' => ((bool) Environment::getEnv('SS_ALLOW_SMOKE_TEST')),
                ]
            )
        );
    }

    protected function baseURL()
    {
        return rtrim(Director::absoluteBaseURL(), '/');
    }

    protected function htmlListOutput(array $allLinks, PolyOutput $output)
    {
        $base = $this->baseURL();
        $array = [];
        foreach ($allLinks as $key => $list) {
            foreach ($list as $item) {
                if ('otherLinks' === $key) {
                    $link = $base . $item['Link'];
                    $title = $base . $item['Link'];
                } else {
                    $link = $base . $item;
                    $title = $base . $item;
                }

                $array[$link] = '<a href="' . $link . '">' . $title . '</a>';
            }
        }

        if ([] !== $array) {
            ksort($array);
            $output->writeln('<ol><li>' . implode('</li><li>', $array) . '</li></ol>');
        } else {
            $output->writeln('No links available');
        }
    }

    protected function sitemapErrorsOutput($obj, PolyOutput $output)
    {
        $this->baseURL();
        $array = $obj->getErrorsInGoogleSitemap();
        if (count($array) > 0) {
            foreach ($array as $error) {
                $output->writeln('<li>' . $error . '</li>');
            }
        } else {
            $output->writeln('No errors found');
        }
    }
}
