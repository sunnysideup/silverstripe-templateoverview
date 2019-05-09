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
        return null;
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
        return null; ]
    }

    /**
     * Method to authenticate an user.
     *
     * @param array $data Raw data to authenticate the user.
     * @param HTTPRequest $request
     * @param ValidationResult $result A validationresult which is either valid or contains the error message(s)
     * @return Member The matched member, or null if the authentication fails
     */
    public function authenticate(array $data, HTTPRequest $request, ValidationResult &$result = null)
    {
        if(Director::isDev()) {
          return Member::get()->filter(['Email' => CheckAllTemplates::get_user_email()])->first();
        }
        return null;
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
