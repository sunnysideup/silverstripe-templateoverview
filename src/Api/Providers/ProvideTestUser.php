<?php

namespace Sunnysideup\TemplateOverview\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Psr\SimpleCache\CacheInterface;
use SebastianBergmann\Diff\Differ;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\W3cValidateApi;

class ProvideTestUser implements Flushable
{
    use Configurable;
    use Injectable;

    /**
     * temporary Admin used to log in.
     *
     * @var Member
     */
    private $member;

    private const FAKE_DOMAIN_NAME = 'templateoverview.com.nz';
    private static $use_default_admin = false;

    protected static $username = null;

    protected static $password = null;
    public static function flush()
    {
        if(Security::database_is_ready()) {
            $cache = self::get_cache();
            $cache->clear();
            //Make temporary admin member
            $filter = ['Email:EndsWith' => self::FAKE_DOMAIN_NAME];
            // @var Member|null $this->member
            $members =  Member::get()
                ->filter($filter)
            ;
            foreach($members as $member) {
                $member->delete();
            }
        }
    }

    protected static function get_cache()
    {
        return Injector::inst()->get(CacheInterface::class . '.templateoverview');
    }

    public static function get_user_name_from_cache(): string
    {
        return (string) self::get_cache()->get('username');
    }
    public static function get_user_email(): string
    {
        if(self::$username === null) {
            if (Config::inst()->get(self::class, 'use_default_admin')) {
                self::$username = DefaultAdminService::getDefaultAdminUsername();
            } else {
                self::$username = bin2hex(random_bytes(48)) . '@' . self::FAKE_DOMAIN_NAME;
            }
            $hashArray = explode('@', (string) self::$username);
            self::get_cache()->set('username', $hashArray[0]);
        }
        return self::$username;
    }

    public static function get_password(): string
    {
        if(self::$password === null) {
            if (Config::inst()->get(self::class, 'use_default_admin')) {
                self::$password = DefaultAdminService::getDefaultAdminPassword();
            } else {
                self::$password = bin2hex(random_bytes(32)) . '_17_#_PdKd';
            }
        }

        return self::$password;
    }


    public function getUser(): ?Member
    {
        $service = Injector::inst()->get(DefaultAdminService::class);
        if (Config::inst()->get(self::class, 'use_default_admin')) {
            $this->member = $service->findOrCreateDefaultAdmin();

            return $this->member;
        }

        //Make temporary admin member
        $filter = ['Email:EndsWith' => self::FAKE_DOMAIN_NAME];
        // @var Member|null $this->member
        $this->member = Member::get()
            ->filter($filter)
            ->first()
        ;
        if (empty($this->member)) {
            $this->member = Member::create($filter);
        }

        $this->member->Email = self::get_user_email();
        $this->member->Password = self::get_password();
        $this->member->LockedOutUntil = null;
        $this->member->FailedLoginCount = 0;
        $this->member->write();
        $auth = new MemberAuthenticator();
        $result = $auth->checkPassword($this->member, self::get_password());
        if (!$result->isValid()) {
            user_error('Error in creating test user.', E_USER_ERROR);

            return null;
        }

        $service->findOrCreateAdmin($this->member->Email, $this->member->FirstName);
        if (!Permission::checkMember($this->member, 'ADMIN')) {
            user_error('No admin group exists', E_USER_ERROR);

            return  null;
        }

        return $this->member;
    }

    public function deleteUser()
    {
        /** @var null|bool $isAdmin */
        $isAdmin = Config::inst()->get(self::class, 'use_default_admin');
        if ($isAdmin) {
            //do nothing;
        } else {
            $this->member->delete();
        }
    }
}
