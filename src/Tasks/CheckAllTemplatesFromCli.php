<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Control\CheckAllTemplatesResponseController;

class CheckAllTemplatesFromCli extends BuildTask
{
    protected static string $commandName = 'smoketest-cli';

    protected string $title = 'CLI ONLY SMOKETEST: Check URLs for errors';

    protected static string $description = 'Run this task from the command line to check for HTTP response errors (e.g. 404).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!Director::is_cli()) {
            $output->writeln('Only run from CLI');
            return Command::FAILURE;
        }

        $obj = Injector::inst()->get(AllLinks::class);

        $allLinks = $obj->getAllLinks();
        $controller = CheckAllTemplatesResponseController::create();
        foreach ($allLinks['allNonCMSLinks'] as $link) {
            $testLink = $this->createTestLink($link, false);
            $output->writeln(print_r($controller->testOneInner($testLink, false), true));
        }

        foreach ($allLinks['allCMSLinks'] as $link) {
            $testLink = $this->createTestLink($link, true);
            $output->writeln(print_r($controller->testOneInner($testLink, false), true));
        }

        return Command::SUCCESS;
    }

    protected function createTestLink(string $link, bool $isCmsLink = false)
    {
        return $this->baseURL() . $link;
    }

    protected function baseURL()
    {
        return rtrim(Director::absoluteBaseURL(), '/');
    }
}
