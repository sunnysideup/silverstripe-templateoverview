<?php

namespace Sunnysideup\TemplateOverview\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use Sunnysideup\TemplateOverview\Tasks\CheckAllTemplates;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Environment;
use SilverStripe\Security\Security;
use SilverStripe\Security\MemberAuthenticator\CookieAuthenticationHandler;

class LoginAndRedirect extends Controller
{
    private static $allowed_actions = [
        'login' => '->isDev'
    ];

    function login($request)
    {
        $url = $request->getVar('BackURL');
        $member = CheckAllTemplates::get_test_user();
        Security::setCurrentUser($member);
        Injector::inst()->get(CookieAuthenticationHandler::class)
            ->logIn($member, $persist = true);
        // die($url);
        return $this->redirect($url);
    }

    public function isDev()
    {
        if(Environment::getEnv('ALLOW_SMOKE_TEST')) {
            return Director::isDev();
        } else {
            die('Please set ALLOW_SMOKE_TEST in your environment variables');
        }
    }

}
