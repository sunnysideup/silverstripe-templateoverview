<?php

namespace Sunnysideup\TemplateOverview\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Util\IPUtils;
use Sunnysideup\TemplateOverview\Tasks\CheckAllTemplates;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Security;
use SilverStripe\Security\MemberAuthenticator\CookieAuthenticationHandler;

class LoginAndRedirect extends Controller
{
    private static $allowed_actions = [
        'login' => '->isDev',
    ];

    private static $allowed_ips = [
        '127.0.0.1',
    ];

    function login($request)
    {
        $url = $request->getVar('BackURL');
        $member = CheckAllTemplatesResponseController::get_test_user();
        Security::setCurrentUser($member);
        Injector::inst()->get(CookieAuthenticationHandler::class)
            ->logIn($member, $persist = true);
        // die($url);
        return $this->redirect($url);
    }

    public function isDev()
    {
        if(Environment::getEnv('SS_ALLOW_SMOKE_TEST')) {
            $allowedIPs = Config::inst()->get(self::class, 'allowed_ips');
            if(IPUtils::checkIP($this->request->getIP(), $allowedIPs)) {
                return Director::isDev();
            } else {
                die('Please include your ip address in LoginAndRedirect.allowed_ips: '.$this->request->getIP(). '. Currently set are: '.implode(', ', $allowedIPs));
            }
        } else {
            die('Please set SS_ALLOW_SMOKE_TEST in your environment variables to use this service.');
        }
    }

}
