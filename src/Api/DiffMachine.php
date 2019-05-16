<?php
namespace Sunnysideup\TemplateOverview\Api;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;



class DiffMachine
{
    public static function compare($content, $otherContent)
    {
        $builder = new DiffOnlyOutputBuilder(
            "--- Original\n+++ New\n"
        );

        $differ = new Differ($builder);

        $lines = $differ->diff(
            self::cleanup_content('aaa'.$content),
            self::cleanup_content($otherContent)
        );
        $linesArray = explode("\n", $lines);
        $unsets = [];
        foreach($linesArray as $key => $line) {
            if(strlen($line) < 3) {
                $unsets[$key] = $key;
                continue;
            }
            $isRemoved = false;
            if(substr($line, 0 ,1) === '-') {
                $isRemoved = true;
            }
            if($isRemoved) {
                $class = 'diff-removed';
            } else {
                $class = 'diff-added';
            }
            $linesArray[$key] = '<p class="'.$class.'">'.htmlspecialchars($line).'</p>';
        }
        foreach($unsets as $key) {
            unset($linesArray[$key]);
        }

        return implode("\n", $linesArray);
    }

    private static function cleanup_content($content)
    {
        $contentArray = explode("\n", $content);
        foreach($contentArray as $key => $contentLine) {
            $contentArray[$key] = preg_replace('/\s+/', ' ',$contentLine);
        }

        return implode("\n", $contentArray);
    }
}
