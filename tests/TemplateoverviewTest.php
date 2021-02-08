<?php

use SilverStripe\Dev\SapphireTest;

class TemplateoverviewTest extends SapphireTest
{
    protected $usesDatabase = false;

    protected $requiredExtensions = [];

    public function TestDevBuild()
    {
        $exitStatus = shell_exec('vendor/bin/sake dev/build flush=all  > dev/null; echo $?');
        $exitStatus = intval(trim($exitStatus));
        $this->assertSame(0, $exitStatus);
    }
}
