<?php

namespace Sunnysideup\TemplateOverview\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\IdentityStore;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\TemplateOverview\Api\ProvideTestUser;
use Symfony\Component\HttpFoundation\IpUtils;

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
        if ($testvalue && $testvalue === $hash) {
            $member = Member::get()->filter(['Email:StartsWith' => $testvalue . '@'])->first();
            if ($member) {
                Security::setCurrentUser($member);
                Injector::inst()->get(IdentityStore::class)->logIn($member, true);
                return $this->redirect($url);
            } else {
                user_error('Could not find user with email ' . $testvalue . '@. Please try again.', E_USER_ERROR);
                return null;
            }
        } else {
            user_error('Could not log you in while trying to access ' . $hash . ' should be the same as ' . $testvalue . '. Please try again.', E_USER_ERROR);
            return null;
        }
    }

    public function isDev()
    {
        if (Director::isDev() && Environment::getEnv('SS_ALLOW_SMOKE_TEST')) {
            $allowedIPs = Config::inst()->get(self::class, 'allowed_ips');
            $test = false;
            if (class_exists(IpUtils::class)) {
                $test = IpUtils::checkIP($this->request->getIP(), $allowedIPs);
            } else {
                $test = in_array($this->request->getIP(), $allowedIPs, true);
            }
            if ($test) {
                return Director::isDev();
            }

            user_error(
                'Please include your ip address in LoginAndRedirect.allowed_ips: ' .
                    $this->request->getIP() . '.
                    Currently set are: ' . implode(', ', $allowedIPs),
                E_USER_ERROR
            );

            return null;
        }

        user_error(
            'Please set SS_ALLOW_SMOKE_TEST in your environment variables to use this service.',
            E_USER_ERROR
        );
        return null;
    }
}
