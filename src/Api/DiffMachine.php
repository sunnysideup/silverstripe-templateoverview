<?php

use SebastianBergmann\Diff\Differ;
namespace Sunnysideup\TemplateOverview\Api;

class ComparisonMachine
{
    public static function compare($content, $otherURL)
    {
        $otherContent = file_get_contents($otherURL);
        $differ = new Differ;
        return $differ->diff($content, $otherContent);
    }
}
