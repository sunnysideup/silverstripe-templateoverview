<?php

namespace Sunnysideup\TemplateOverview\Security;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Authenticator as AuthenticatorInterface;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\LogoutHandler;
use SilverStripe\Control\Director;

use SilverStripe\Core\Extensible;

use Sunnysideup\TemplateOverview\Tasks\CheckAllTemplates;
use Sunnysideup\TemplateOverview\Security\LoginHandler;

class Authenticator implements AuthenticatorInterface
{
    use Injectable;


    /**
     * @return bool false if the authenticator shouldn't be registered
     */
    public function __construct()
    {
        return true;
    }

    /**
     * Returns the services supported by this authenticator
     *
     * The number should be a bitwise-OR of 1 or more of the following constants:
     * Authenticator::LOGIN, Authenticator::LOGOUT, Authenticator::CHANGE_PASSWORD,
     * Authenticator::RESET_PASSWORD, or Authenticator::CMS_LOGIN
     *
     * @return int
     */
    public function supportedServices()
    {
        return Authenticator::CMS_LOGIN | Authenticator::LOGIN;
    }
    public function authenticate(array $data, HttpRequest $request, ValidationResult &$result = null)
    {
        return Member::get()->first();
    }

    /**
     * Determine if this authenticator is applicable to the current request
     *
     * @param HTTPRequest $request
     * @return bool
     */
    public function isApplicable(HTTPRequest $request)
    {
        $user = Member::get()->first();
        return !empty($user);
    }



    /**
     * Return RequestHandler to manage the log-in process.
     *
     * The default URL of the RequestHandler should return the initial log-in form, any other
     * URL may be added for other steps & processing.
     *
     * URL-handling methods may return an array [ "Form" => (form-object) ] which can then
     * be merged into a default controller.
     *
     * @param string $link The base link to use for this RequestHandler
     * @return RealMeLoginHandler
     */
    public function getLoginHandler($link)
    {
        return LoginHandler::create($link);
    }

    /**
     * Return the RequestHandler to manage the log-out process.
     *
     * The default URL of the RequestHandler should log the user out immediately and destroy the session.
     *
     * @param string $link The base link to use for this RequestHandler
     * @return LogoutHandler
     */
    public function getLogOutHandler($link)
    {
        // No-op
    }

    /**
     * Return RequestHandler to manage the change-password process.
     *
     * The default URL of the RequetHandler should return the initial change-password form,
     * any other URL may be added for other steps & processing.
     *
     * URL-handling methods may return an array [ "Form" => (form-object) ] which can then
     * be merged into a default controller.
     *
     * @param string $link The base link to use for this RequestHnadler
     */
    public function getChangePasswordHandler($link)
    {
        return null; // Cannot provide change password facilities for RealMe
    }

    /**
     * @param string $link
     * @return mixed
     */
    public function getLostPasswordHandler($link)
    {
        return null; // Cannot provide lost password facilities for RealMe
    }

    /**
     * Check if the passed password matches the stored one (if the member is not locked out).
     *
     * Note, we don't return early, to prevent differences in timings to give away if a member
     * password is invalid.
     *
     * Passwords are not part of this authenticator
     *
     * @param Member $member
     * @param string $password
     * @param ValidationResult $result
     * @return ValidationResult
     */
    public function checkPassword(Member $member, $password, ValidationResult &$result = null)
    {
        // No-op
    }

}
