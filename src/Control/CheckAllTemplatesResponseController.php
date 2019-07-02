<?php

namespace Sunnysideup\TemplateOverview\Control;

use ReflectionClass;
use ReflectionMethod;
use Psr\SimpleCache\CacheInterface;

use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\W3cValidateApi;
use Sunnysideup\TemplateOverview\Api\DiffMachine;


use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Member;
use SilverStripe\Security\Group;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Flushable;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Admin\CMSMenu;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPApplication;
use SilverStripe\Control\HTTPRequestBuilder;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\TaskRunner;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\CoreKernel;
use SilverStripe\Core\Startup\ErrorControlChainMiddleware;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;

use SilverStripe\Security\MemberAuthenticator\MemberAuthenticator;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Permission;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;

/**
 * @description (see $this->description)
 *
 * @authors: Andrew Pett [at] sunny side up .co.nz, Nicolaas [at] Sunny Side Up .co.nz
 * @package: templateoverview
 * @sub-package: tasks
 **/

class CheckAllTemplatesResponseController extends Controller implements Flushable
{

    /**
     * Defines methods that can be called directly
     * @var array
     */
    private static $allowed_actions = [
        'testone' => 'ADMIN',
    ];

    public static function flush()
    {
        $cache = Injector::inst()->get(CacheInterface::class . '.templateoverview');
        $cache->clear();
    }

    private static $use_default_admin = true;

    private static $username = '';

    private static $password = '';

    private static $url_segment = 'templateoverviewsmoketestresponse';


    private static $use_w3_validation = false;

    public static function get_user_email()
    {
        $userName = Config::inst()->get(CheckAllTemplatesResponseController::class, 'username');
        if (! $userName) {
            if (Config::inst()->get(CheckAllTemplatesResponseController::class, 'use_default_admin')) {
                $userName = DefaultAdminService::getDefaultAdminUsername();
            } else {
                $userName = 'smoketest@test.com.ssu';
            }
        }

        return $userName;
    }

    public static function get_password()
    {
        $password = Config::inst()->get(CheckAllTemplatesResponseController::class, 'password');
        if (! $password) {
            if (Config::inst()->get(CheckAllTemplatesResponseController::class, 'use_default_admin')) {
                $password = DefaultAdminService::getDefaultAdminPassword();
            } else {
                $cache = Injector::inst()->get(CacheInterface::class . '.templateoverview');
                if (! $cache->has('password')) {
                    $password = strtolower('aa'.substr(uniqid(), 0, 8)).'_.,'.strtoupper('BB'.substr(uniqid(), 0, 8));
                    $cache->set('password', $password);
                }
                $password = $cache->get('password');
            }
        }

        return $password;
    }

    public static function get_test_user()
    {
        return Injector::inst()->get(CheckAllTemplatesResponseController::class)->getTestUser();
    }


    private $guzzleCookieJar = null;

    private $guzzleClient = null;

    private $guzzleHasError = false;

    private $isSuccess = false;


    /**
     * temporary Admin used to log in.
     * @var Member
     */
    private $member = null;

    private $rawResponse = '';

    /**
     * @var Boolean
     */
    private $debug = false;


    /**
     * Main function
     * has two streams:
     * 1. check on url specified in GET variable.
     * 2. create a list of urls to check
     *
     * @param HTTPRequest
     */
    public function testone($request)
    {
        $isCMSLink = $request->getVar('iscmslink') ? true : false;
        $testURL = $request->getVar('test') ? : null;

        // 1. actually test a URL and return the data
        if ($testURL) {
            $this->guzzleSetup();
            $this->getTestUser();
            $content = $this->testURL($testURL);
            $this->deleteUser();
            $this->cleanup();
            print $content;
            if(! Director::is_ajax()) {
                ;
                $diff =  '';
                $comparisonBaseURL = Config::inst()->get(CheckAllTemplatesResponseController::class, 'comparision_base_url');
                $width = '98%';
                $style = 'border: none;';
                if($comparisonBaseURL) {
                    $width = '48%';
                    $style = 'float: left;';
                    if($this->isSuccess && !$isCMSLink) {
                        $otherURL = $comparisonBaseURL . $testURL;
                        $testContent = str_replace(Director::absoluteBaseURL(), $comparisonBaseURL, $this->rawResponse);
                        $rawResponseOtherSite = @file_get_contents($otherURL);
                        $diff = DiffMachine::compare(
                            $testContent,
                            $rawResponseOtherSite
                        );
                        $rawResponseOtherSite = Convert::raw2htmlatt(str_replace('\'', '\\\'', $rawResponseOtherSite));
                        $diff = '
                        <iframe id="iframe2" width="'.$width.'%" height="7000" srcdoc=\''.$rawResponseOtherSite.'\' style="float: right;"></iframe>

                        <hr style="clear: both; margin-top: 20px; padding-top: 20px;" />
                        <h1>Diff</h1>
                        <link href="/resources/vendor/sunnysideup/templateoverview/client/css/checkalltemplates.css" rel="stylesheet" type="text/css" />
                        '.$diff;
                    }
                }
                $rawResponse = Convert::raw2htmlatt(str_replace('\'', '\\\'', $this->rawResponse));
                echo '
                    <h1>Response</h1>
                    <iframe id="iframe" width="'.$width.'%" height="7000" srcdoc=\''.$rawResponse.'\' style="'.$style.'"></iframe>
                ';
                echo $diff;
            }
            return;
        } else {
            user_error('no test url provided.');
        }

    }

    /**
     * ECHOES the result of testing the URL....
     * @param String $url
     */
    private function testURL($url)
    {
        if (strlen(trim($url)) < 1) {
            user_error("empty url"); //Checks for empty strings.
        }
        if (AllLinks::is_admin_link($url)) {
            $validate = false;
        } else {
            $validate = Config::inst()->get(CheckAllTemplatesResponseController::class, 'use_w3_validation');
        }
        $testURL = Director::absoluteURL('/templateoverviewloginandredirect/login/?BackURL=');
        $testURL .= urlencode($url);
        $this->guzzleSetup();

        $start = microtime(true);
        $response = $this->guzzleSendRequest($testURL);
        $end = microtime(true);

        $data = [
            'status' => 'success',
            'httpResponse' => '200',
            'content' => '',
            'responseTime' => round($end - $start, 4),
            'w3Content' => '',
        ];

        $httpResponse = $response->getStatusCode();
        $error = $response->getReasonPhrase();
        if ($this->guzzleHasError) {
            //we already have the body ...
        } else {
            $this->rawResponse = $response->getBody();
        }

        $data['httpResponse'] = $httpResponse;

        if ($httpResponse == "401") {
            $data['status'] = 'error';
            $data['content'] = 'Could not access: ' . $url;

            return json_encode($data);
        }

        //uncaught errors ...
        if ($this->rawResponse && substr($this->rawResponse, 0, 12) == "Fatal error") {
            $data['status'] = 'error';
            $data['content'] = $this->rawResponse;
        } elseif ($httpResponse == 200 && $this->rawResponse && strlen($this->rawResponse) < 500) {
            $data['status'] = 'error';
            $data['content'] = 'SHORT RESPONSE: ' . $this->rawResponse;
        }

        $data['w3Content'] = 'n/a';

        if ($httpResponse != 200) {
            $data['status'] = 'error';
            $data['content'] .= 'unexpected response: ' . $error . $this->rawResponse;
        } else {
            $this->isSuccess = true;
            if ($validate) {
                $w3Obj = new W3cValidateApi();
                $data['w3Content'] = $w3Obj->W3Validate("", $this->rawResponse);
            }
        }

        if (Director::is_ajax()) {
            return json_encode($data);
        }
        $content = '';
        $content .= '<p><strong>URL:</strong> ' . $url . '</p>';
        $content .= '<p><strong>Status:</strong> ' . $data['status'] . '</p>';
        $content .= '<p><strong>HTTP response:</strong> ' . $data['httpResponse'] . '</p>';
        $content .= '<p><strong>Content:</strong> ' . htmlspecialchars($data['content']) . '</p>';
        $content .= '<p><strong>Response time:</strong> ' . $data['responseTime'] . '</p>';
        $content .= '<p><strong>W3 Content:</strong> ' . $data['w3Content'] . '</p>';

        return $content;
    }


    /**
     * creates the basic curl
     *
     */
    private function guzzleSetup($type = "GET")
    {
        // $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
        // $post = $type == "GET" ? false : true;
        //
        // $strCookie = 'PHPSESSID=' . session_id() . '; path=/';
        // $options = array(
        //     CURLOPT_CUSTOMREQUEST  => $type,        //set request type post or get
        //     CURLOPT_POST           => $post,        //set to GET
        //     CURLOPT_USERAGENT      => $user_agent, //set user agent
        //     CURLOPT_COOKIE         => $strCookie, //set cookie file
        //     CURLOPT_COOKIEFILE     => "cookie.txt", //set cookie file
        //     CURLOPT_COOKIEJAR      => "cookie.txt", //set cookie jar
        //     CURLOPT_RETURNTRANSFER => true,     // return web page
        //     CURLOPT_HEADER         => false,    // don't return headers
        //     CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        //     CURLOPT_ENCODING       => "",       // handle all encodings
        //     CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        //     CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        //     CURLOPT_TIMEOUT        => 120,      // timeout on response
        //     CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        // );
        //
        // $this->ch = curl_init();
        //
        // curl_setopt_array($this->ch, $options);

        $this->guzzleCookieJar = new CookieJar();
        $this->guzzleClient = new Client(
            [
                'base_uri' => Director::baseURL(),
                'cookies' => true,
            ]
        );
    }

    /**
     *
     * @return
     */
    protected function guzzleSendRequest($url, $type = 'GET')
    {
        $this->guzzleHasError = false;
        $credentials = base64_encode(self::get_user_email().':'.self::get_password());
        try {
            $response = $this->guzzleClient->request(
                'GET',
                $url,
                [
                    'cookies' => $this->guzzleCookieJar,
                    'headers' => [
                        'PHP_AUTH_USER' => self::get_user_email(),
                        'PHP_AUTH_PW' => self::get_password(),
                    ],
                    'auth' => [
                        self::get_user_email(),
                        self::get_password(),
                    ],
                    'Authorization' => ['Basic '.$credentials],
                ]
            );
        } catch (RequestException $exception) {
            $this->rawResponse = $exception->getResponse();
            $this->guzzleHasError = true;
            //echo Psr7\str($exception->getRequest());
            if ($exception->hasResponse()) {
                $response = $exception->getResponse();
                $this->rawResponse = $exception->getResponseBodySummary($response);
            } else {
                $response = null;
            }
        }
        return $response;
    }



    /**
     *
     */
    public function getTestUser()
    {
        if (Config::inst()->get(CheckAllTemplatesResponseController::class, 'use_default_admin')) {
            $this->member = Injector::inst()->get(DefaultAdminService::class)->findOrCreateDefaultAdmin();
            return $this->member;
        } else {
            //Make temporary admin member
            $filter = ["Email" => self::get_user_email()];
            $this->member = Member::get()
                ->filter($filter)
                ->first();
            if ($this->member) {
            } else {
                $this->member = Member::create($filter);
            }
            $this->member->Password = self::get_password();
            $this->member->LockedOutUntil = null;
            $this->member->FirstName = 'Test';
            $this->member->Surname = 'User';
            $this->member->write();
            $auth = new MemberAuthenticator();
            $result = $auth->checkPassword($this->member, self::get_password());
            if (! $result->isValid()) {
                user_error('Error in creating test user.', E_USER_ERROR);
                die('---');
            }

            $adminGroup = DefaultAdminService::findOrCreateAdminGroup();
            if (!$adminGroup) {
                user_error("No admin group exists", E_USER_ERROR);
                die('---');
            }
            $this->member->Groups()->add($adminGroup);
            if (Permission::checkMember($this->member, 'ADMIN')) {
                user_error("No admin group exists", E_USER_ERROR);
                die('---');
            }

            return $this->member;
        }
    }


    private function deleteUser()
    {
        if (Config::inst()->get(CheckAllTemplatesResponseController::class, 'use_default_admin')) {
            //do nothing;
        } else {
            if ($this->member) {
                $this->member->delete();
            }
        }
    }


    /**
     * cleans up the curl connection.
     *
     */
    private function cleanup()
    {
    }



    private function debugme($lineNumber, $variable = "")
    {
        if ($this->debug) {
            echo "<br />" . $lineNumber . ": " . round(memory_get_usage() / 1048576) . "MB" . "=====" . print_r($variable, 1);
            ob_flush();
            flush();
        }
    }
}
