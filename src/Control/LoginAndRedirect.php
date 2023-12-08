<?php

namespace Sunnysideup\TemplateOverview\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Util\IPUtils;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\CookieAuthenticationHandler;
use SilverStripe\Security\Security;
use Sunnysideup\TemplateOverview\Api\ProvideTestUser;

/**
 * Class \Sunnysideup\TemplateOverview\Control\LoginAndRedirect
 *
 */
class LoginAndRedirect extends Controller
{
    private static $allowed_actions = [
        'index' => 'ADMIN',
        'login' => '->isDev',
    ];

    private static $allowed_ips = [
        '127.0.0.1',
    ];

    public function login($request)
    {
        $url = $request->getVar('BackURL');
        $hash = $request->getVar('hash');
        $testvalue = ProvideTestUser::get_user_name_from_cache();
        if($testvalue && $testvalue === $hash) {
            $member = Member::get()->filter(['Email:StartsWith' => $testvalue . '@'])->first();
            if($member) {
                Security::setCurrentUser($member);
                Injector::inst()->get(IdentityStore::class)->logIn($member, true);
                return $this->redirect($url);
            }
        }
        user_error('Could not log you in while trying to access ' . $url . '. Please try again.', E_USER_ERROR);
    }

    public function isDev()
    {
        if (Environment::getEnv('SS_ALLOW_SMOKE_TEST')) {
            $allowedIPs = Config::inst()->get(self::class, 'allowed_ips');
            if (IPUtils::checkIP($this->request->getIP(), $allowedIPs)) {
                return Director::isDev();
            }

            user_error(
                'Please include your ip address in LoginAndRedirect.allowed_ips: ' .
                    $this->request->getIP() . '.
                    Currently set are: ' . implode(', ', $allowedIPs),
                E_USER_ERROR
            );

            return;
        }

        user_error(
            'Please set SS_ALLOW_SMOKE_TEST in your environment variables to use this service.',
            E_USER_ERROR
        );
    }
}
