<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

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

    private const string FAKE_DOMAIN_NAME = 'templateoverview.com.nz';

    private static $use_default_admin = false;

    protected static $username;

    protected static $password;

    public static function flush()
    {
        if (Security::database_is_ready()) {
            $cache = self::get_cache();
            $cache->clear();
            //Make temporary admin member
            $filter = ['Email:EndsWith' => self::FAKE_DOMAIN_NAME];
            // @var Member|null $this->member
            $members = Member::get()
                ->filter($filter);
            foreach ($members as $member) {
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
        if (self::$username === null) {
            if (Config::inst()->get(self::class, 'use_default_admin')) {
                self::$username = DefaultAdminService::getDefaultAdminUsername();
            } else {
                $cache = self::get_cache();
                $local = (string) $cache->get('username');
                if ($local === '') {
                    $local = bin2hex(random_bytes(48));
                    $cache->set('username', $local);
                }
                self::$username = $local . '@' . self::FAKE_DOMAIN_NAME;
            }
        }

        return self::$username;
    }

    public static function get_password(): string
    {
        if (self::$password === null) {
            if (Config::inst()->get(self::class, 'use_default_admin')) {
                self::$password = DefaultAdminService::getDefaultAdminPassword();
            } else {
                $cache = self::get_cache();
                $pw = (string) $cache->get('password');
                if ($pw === '') {
                    $pw = bin2hex(random_bytes(32)) . '_17_#_PdKd';
                    $cache->set('password', $pw);
                }
                self::$password = $pw;
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
    ->filter(['Email:EndsWith' => self::FAKE_DOMAIN_NAME])
    ->first();
        if (! $this->member) {
            $this->member = Member::create();
        }

        $this->member->Email = self::get_user_email();
        $this->member->Password = self::get_password();
        $this->member->LockedOutUntil = null;   // was '' — clears lockout cleanly
        $this->member->FailedLoginCount = 0;
        $this->member->write();

        $auth = new MemberAuthenticator();
        $result = $auth->checkPassword($this->member, self::get_password());
        if (! $result->isValid()) {
            throw new \RuntimeException(
                'Could not create temporary admin user: '
                . implode('; ', array_column($result->getMessages(), 'message'))
            );
        }

        $service->findOrCreateAdmin($this->member->Email, $this->member->FirstName);
        if (! Permission::checkMember($this->member, 'ADMIN')) {
            throw new \RuntimeException('No admin group exists');
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
