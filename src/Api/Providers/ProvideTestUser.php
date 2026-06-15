<?php

namespace Sunnysideup\TemplateOverview\Api\Providers;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Validation\ValidationException;
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
            // Sweep ALL test members of this domain (here :EndsWith is correct).
            $members = Member::get()
                ->filter(['Email:EndsWith' => self::FAKE_DOMAIN_NAME]);
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
                    // 16 hex chars / 64 bits — plenty for a throwaway dev user,
                    // and far easier to read in logs than a 96-char wall of hex.
                    $local = bin2hex(random_bytes(8));
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
                    $pw = self::generate_password();
                    $cache->set('password', $pw);
                }
                self::$password = $pw;
            }
        }

        return self::$password;
    }

    private static function generate_password(): string
    {
        return bin2hex(random_bytes(32)) . '_17_#_PdKd';
    }

    public function getUser(): ?Member
    {
        $service = Injector::inst()->get(DefaultAdminService::class);
        if (Config::inst()->get(self::class, 'use_default_admin')) {
            $this->member = $service->findOrCreateDefaultAdmin();

            return $this->member;
        }

        $email = self::get_user_email();
        $this->member = $this->findOrCreateMember($email);

        // Happy path: the member already exists with the cached password and is
        // not locked out, so this passes WITHOUT any write(). No write means
        // neither the unique-identifier guard nor the password-history validator
        // can fire — which is exactly what was throwing before.
        if (! $this->canLogIn()) {
            // Recovery (cache was evicted, lockout, password drift, etc.):
            // rotate to a brand-new password — guaranteed not in history — and
            // clear any lockout, then re-check once.
            $this->resetCredentials();
            if (! $this->canLogIn()) {
                throw new \RuntimeException('Could not authenticate temporary admin user.');
            }
        }

        $service->findOrCreateAdmin($this->member->Email, $this->member->FirstName);
        if (! Permission::checkMember($this->member, 'ADMIN')) {
            throw new \RuntimeException('No admin group exists');
        }

        return $this->member;
    }

    /**
     * Look up the test member by EXACT cached email. If found, return it
     * untouched (collapsing any duplicate rows left by past races). Only create
     * + write when it genuinely does not exist — that is the one and only place
     * a password is ever assigned to a member, so the history validator never
     * sees a "reused" password on an existing row.
     */
    private function findOrCreateMember(string $email): Member
    {
        $matches = Member::get()->filter(['Email' => $email]);
        $member = $matches->first();

        if ($member) {
            if ($matches->count() > 1) {
                foreach ($matches as $dupe) {
                    if ((int) $dupe->ID !== (int) $member->ID) {
                        $dupe->delete();
                    }
                }
            }

            return $member;
        }

        $member = Member::create();
        $member->Email = $email;
        $member->Password = self::get_password();

        try {
            $member->write();
        } catch (ValidationException $e) {
            // Lost a concurrent create race (page + ajax=1 firing together) —
            // adopt the row the other request just committed.
            $existing = Member::get()->filter(['Email' => $email])->first();
            if (! $existing) {
                throw $e;
            }
            $member = $existing;
        }

        return $member;
    }

    private function canLogIn(): bool
    {
        $auth = new MemberAuthenticator();

        return $auth->checkPassword($this->member, self::get_password())->isValid();
    }

    /**
     * Rotate to a fresh password (cache + static + member) and clear any
     * lockout. A brand-new random password cannot collide with the member's
     * password history, so this write is always accepted.
     */
    private function resetCredentials(): void
    {
        $pw = self::generate_password();
        self::get_cache()->set('password', $pw);
        self::$password = $pw;

        $this->member->LockedOutUntil = null;
        $this->member->FailedLoginCount = 0;
        $this->member->Password = $pw;
        $this->member->write();
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
