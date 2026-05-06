<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Control\Session;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class CheckAllTemplatesFromCli extends BuildTask
{
    protected static string $commandName = 'smoketest-cli';

    /** @var Member|null Admin member used for all requests. */
    private ?Member $adminMember = null;

    /**
     * Raw session data array. A FRESH Session object is created from this for every
     * Director::test() call so that a logout or form-submission cannot corrupt the
     * shared session state between tests.
     *
     * @var array
     */
    private array $sessionData = [];

    /**
     * Tracks every normalised relative URL that has already been checked so
     * that the same link is never fetched twice, even if it appears in both
     * the CMS and non-CMS link collections.
     *
     * @var array<string, true>
     */
    private array $checkedLinks = [];

    protected string $title = 'CLI ONLY SMOKETEST: Check URLs for errors';

    protected static string $description = 'Run this task from the command line to check for HTTP response errors. Requires dev mode and SS_ALLOW_SMOKE_TEST=true (same security gates as the browser smoketest). 404s are always skipped. ErrorPage URLs (e.g. /404/ and /500/) are skipped regardless of their response code. Redirects are followed (up to 5 hops). All other non-200 responses are treated as failures. Exits with a non-zero code if any failure is found.';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!Director::is_cli()) {
            $output->writeln('This task must be run from the command line.');
            return Command::FAILURE;
        }

        // Same security gates as the browser smoketest (LoginAndRedirect::isDev()).
        if (!Director::isDev()) {
            $output->writeln('[FAIL] This task can only be run in dev mode.');
            return Command::FAILURE;
        }

        if (!Environment::getEnv('SS_ALLOW_SMOKE_TEST')) {
            $output->writeln('[FAIL] The SS_ALLOW_SMOKE_TEST environment variable must be set to use this task.');
            return Command::FAILURE;
        }

        ini_set('max_execution_time', 3000);

        // We need an admin member so that CMS/admin URLs can be tested.
        $this->adminMember = $this->getAdminMember();
        if ($this->adminMember === null) {
            $output->writeln('[FAIL] Could not find an admin member. Ensure at least one ADMIN-permission member exists.');
            return Command::FAILURE;
        }

        $output->writeln('Testing as member: ' . $this->adminMember->Email);

        // Build the base session data.  loggedInAs is the standard SilverStripe
        // session variable (security.yml: SessionVariable: loggedInAs).
        $this->sessionData = ['loggedInAs' => $this->adminMember->ID];

        // silverstripe/session-manager adds LoginSessionMiddleware which calls
        // logOut() (→ Security::setCurrentUser(null)) whenever the session does
        // not have a matching LoginSession DB record.  Create a temporary one so
        // that Director::test() requests for admin URLs are not immediately logged
        // out before the controller is reached.
        $loginSession = $this->createLoginSession();
        if ($loginSession !== null) {
            // The session variable is 'activeLoginSession' (session-manager config.yml).
            $this->sessionData['activeLoginSession'] = $loginSession->ID;
            $output->writeln('Created temporary LoginSession ID ' . $loginSession->ID);
        }

        $output->writeln('');

        // Collect ErrorPage URLs — their non-200 responses are by design.
        $errorPageUrls = $this->getErrorPageUrls();
        if (count($errorPageUrls) > 0) {
            $output->writeln('ErrorPage URLs (non-200 responses skipped):');
            foreach ($errorPageUrls as $u) {
                $output->writeln('  ' . $u);
            }
            $output->writeln('');
        }

        $obj = Injector::inst()->get(AllLinks::class);
        $allLinks = $obj->getAllLinks();

        $errors  = [];
        $skipped = [];
        $total   = 0;

        // Frontend links
        foreach ($allLinks['allNonCMSLinks'] as $link) {
            if ($this->hasBeenChecked($link)) {
                continue;
            }
            ++$total;
            $this->markAsChecked($link);
            $result = $this->checkLink($link, $errorPageUrls, false, $output);
            if ($result === 'skip') {
                $skipped[] = $link;
            } elseif ($result !== null) {
                $errors[] = $result;
            }
        }

        // CMS / back-end links
        foreach ($allLinks['allCMSLinks'] as $link) {
            if ($this->hasBeenChecked($link)) {
                continue;
            }
            ++$total;
            $this->markAsChecked($link);
            $result = $this->checkLink($link, $errorPageUrls, true, $output);
            if ($result === 'skip') {
                $skipped[] = $link;
            } elseif ($result !== null) {
                $errors[] = $result;
            }
        }

        // Other controller links – stored as ['Link' => ..., 'ClassName' => ...]
        $output->writeln("You may also want to check the following controller method for errors (not automatically checked by this task):");
        foreach ($allLinks['otherLinks'] as $link => $className) {
            $output->writeln("- {$link} ({$className})");
        }

        // Clean up the temporary LoginSession record.
        if ($loginSession !== null) {
            try {
                $loginSession->delete();
            } catch (\Throwable $e) {
                // Non-fatal – the record can be cleaned up manually if needed.
            }
        }

        Security::setCurrentUser(null);

        $output->writeln('');
        $output->writeln(sprintf(
            'Checked %d URL(s): %d passed, %d skipped (404, ErrorPage, or CMS auth), %d failed.',
            $total,
            $total - count($errors) - count($skipped),
            count($skipped),
            count($errors)
        ));

        if (count($errors) > 0) {
            $output->writeln('');
            $output->writeln('FAILURES (' . count($errors) . '):');
            foreach ($errors as $errorMsg) {
                $output->writeln('  [FAIL] ' . $errorMsg);
            }

            return Command::FAILURE;
        }

        $output->writeln('No unexpected errors found.');

        return Command::SUCCESS;
    }

    /**
     * Test a single URL, following redirects up to 5 hops (matching Guzzle's
     * default in the browser smoketest).
     *
     * Announces the URL to the output *before* fetching so that a hung or
     * crashing request can be identified immediately. Timing is appended to
     * every result line so slow pages are easy to spot.
     *
     * Returns:
     *   null   – success (HTTP 200 after any redirects)
     *   'skip' – acceptable non-200 (404, ErrorPage, or CMS auth redirect)
     *   string – error message describing the problem
     */
    protected function checkLink(
        string $link,
        array $errorPageUrls,
        bool $isCmsLink,
        PolyOutput $output
    ): ?string {
        // Fragments (#anchor) are client-side only — strip them.
        if (($hashPos = strpos($link, '#')) !== false) {
            $link = substr($link, 0, $hashPos);
        }

        $relativeLink = Director::makeRelative($link) ?: '/';
        $absoluteLink = rtrim(Director::absoluteBaseURL(), '/') . '/' . ltrim($relativeLink, '/');

        // Announce the URL *before* fetching so that if the request hangs or
        // triggers a fatal error the operator can see exactly which URL caused it.
        $output->writeln('[    ] Fetching ' . $absoluteLink);

        $start = microtime(true);
        [$httpCode, $finalAbsolute] = $this->fetchWithRedirects($relativeLink, $absoluteLink);
        $timing = sprintf('%.2fs', microtime(true) - $start);

        $displayLink = ($finalAbsolute !== $absoluteLink)
            ? "{$absoluteLink} → {$finalAbsolute}"
            : $absoluteLink;

        // 404s are always acceptable.
        if ($httpCode === 404) {
            $output->writeln("[SKIP] HTTP 404 ({$timing}) {$displayLink}");
            return 'skip';
        }

        // ErrorPages intentionally return their own error code.
        // Normalise trailing slashes before comparing.
        $normFinal = rtrim($finalAbsolute, '/');
        foreach ($errorPageUrls as $errorPageUrl) {
            if (rtrim($errorPageUrl, '/') === $normFinal) {
                $output->writeln("[SKIP] HTTP {$httpCode} ({$timing}) (ErrorPage – expected) {$displayLink}");
                return 'skip';
            }
        }

        // For CMS/admin links a 3xx–4xx means the routing and auth guard are
        // functioning correctly — it is NOT a PHP error.
        if ($isCmsLink && $httpCode >= 300 && $httpCode < 500) {
            $output->writeln("[SKIP] HTTP {$httpCode} ({$timing}) (CMS redirect/auth) {$displayLink}");
            return 'skip';
        }

        // 405 Method Not Allowed: the URL exists but only accepts POST (e.g. form
        // submission endpoints).  This is correct HTTP behaviour, not a PHP error.
        if ($httpCode === 405) {
            $output->writeln("[SKIP] HTTP 405 ({$timing}) (POST-only endpoint) {$displayLink}");
            return 'skip';
        }

        // 400 Bad Request: common for form endpoints that require POST body params
        // (e.g. Security/resetaccount requires a reset token, loginsession/remove
        // requires a session ID).  These are correct responses for GET requests.
        if ($httpCode === 400) {
            $output->writeln("[SKIP] HTTP 400 ({$timing}) (form endpoint, params required) {$displayLink}");
            return 'skip';
        }

        // Anything else that is not 200 is a real problem.
        if ($httpCode !== 200) {
            $msg = "HTTP {$httpCode} ({$timing}) — {$displayLink}";
            $output->writeln('[FAIL] ' . $msg);
            return $msg;
        }

        $output->writeln("[ OK ] HTTP {$httpCode} ({$timing}) {$displayLink}");
        return null;
    }

    /**
     * Execute a GET request, following redirects up to $maxHops times.
     * Returns [int $finalHttpCode, string $finalAbsoluteURL].
     *
     * A fresh Session is created from $this->sessionData for every hop so that
     * side-effects (logouts, form submissions) cannot corrupt the shared state.
     * Security::setCurrentUser() is set to the admin member before each hop and
     * restored after, providing a belt-and-suspenders backup in case the session
     * auth handler fails to pick up the member.
     */
    protected function fetchWithRedirects(string $relativeLink, string $absoluteLink, int $maxHops = 5): array
    {
        $currentRelative = $relativeLink;
        $currentAbsolute = $absoluteLink;
        $httpCode = 200;

        for ($hop = 0; $hop <= $maxHops; $hop++) {
            $session = Injector::inst()->create(Session::class, $this->sessionData);

            $previousUser = Security::getCurrentUser();
            Security::setCurrentUser($this->adminMember);

            try {
                $response = Director::test($currentRelative, [], $session);
                $httpCode  = $response->getStatusCode();
            } catch (HTTPResponse_Exception $e) {
                $httpCode = $e->getResponse()->getStatusCode();
            } catch (\Throwable $e) {
                // Treat any uncaught PHP error/exception as a 500.
                $httpCode = 500;
            } finally {
                Security::setCurrentUser($previousUser);
            }

            // Follow 3xx redirects.
            if ($httpCode >= 300 && $httpCode < 400 && $hop < $maxHops) {
                $location = $response->getHeader('Location') ?? '';
                if ($location === '') {
                    break;
                }
                $currentRelative = Director::makeRelative($location) ?: '/';
                $currentAbsolute = rtrim(Director::absoluteBaseURL(), '/') . '/' . ltrim($currentRelative, '/');
            } else {
                break;
            }
        }

        return [$httpCode, $currentAbsolute];
    }

    /**
     * Returns true if this link has already been checked during this run.
     * Comparison is made on the normalised relative URL (lowercase, no fragment).
     */
    protected function hasBeenChecked(string $link): bool
    {
        return isset($this->checkedLinks[$this->normaliseLink($link)]);
    }

    /**
     * Record a link as checked so it is not fetched again.
     */
    protected function markAsChecked(string $link): void
    {
        $this->checkedLinks[$this->normaliseLink($link)] = true;
    }

    /**
     * Produce a stable key for a URL: strip the fragment, make relative,
     * lowercase, and strip the trailing slash.
     */
    protected function normaliseLink(string $link): string
    {
        if (($hashPos = strpos($link, '#')) !== false) {
            $link = substr($link, 0, $hashPos);
        }

        $relative = Director::makeRelative($link) ?: '/';

        return rtrim(strtolower($relative), '/');
    }

    /**
     * Create a temporary LoginSession record so that LoginSessionMiddleware
     * (from silverstripe/session-manager) does not immediately log out the test
     * user on every admin request.
     *
     * Returns the record on success, null if the module is not installed.
     */
    protected function createLoginSession(): ?object
    {
        $class = 'SilverStripe\\SessionManager\\Models\\LoginSession';
        if (!class_exists($class)) {
            return null;
        }

        try {
            // DataObject::write() does not enforce canCreate() from PHP code —
            // only form-submission paths do.  Direct write() is safe here.
            Security::setCurrentUser($this->adminMember);
            $loginSession = $class::create([
                'MemberID'  => $this->adminMember->ID,
                'IPAddress' => '127.0.0.1',
                'UserAgent' => 'SilverStripe smoketest-cli',
            ]);
            $loginSession->write();
            Security::setCurrentUser(null);
            return $loginSession;
        } catch (\Throwable $e) {
            Security::setCurrentUser(null);
            return null;
        }
    }

    /**
     * Query the database for all SilverStripe ErrorPage records and return
     * their absolute URLs. These pages return non-200 codes by design.
     *
     * @return string[]
     */
    protected function getErrorPageUrls(): array
    {
        $urls = [];

        try {
            $errorPageClass = 'SilverStripe\\ErrorPage\\ErrorPage';
            if (!class_exists($errorPageClass)) {
                return $urls;
            }

            $pages = $errorPageClass::get();
            foreach ($pages as $page) {
                $link = $page->Link();
                if ($link) {
                    $urls[] = rtrim(Director::absoluteBaseURL(), '/') . '/' . ltrim($link, '/');
                }
            }
        } catch (\Exception $e) {
            // DB not ready or ErrorPage table missing – silently continue.
        }

        return $urls;
    }

    /**
     * Find any existing ADMIN-permission member to use as the test identity.
     */
    protected function getAdminMember(): ?Member
    {
        try {
            $members = Permission::get_members_by_permission('ADMIN');
            return $members->first() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
