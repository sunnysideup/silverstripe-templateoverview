<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use ReflectionClass;
use ReflectionMethod;
use Psr\SimpleCache\CacheInterface;

use Sunnysideup\TemplateOverview\Api\SiteTreeDetails;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\W3cValidateApi;


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

class CheckAllTemplates extends BuildTask implements Flushable
{

    public static function flush()
    {
        $cache = Injector::inst()->get(CacheInterface::class . '.templateoverview');
        $cache->clear();
    }

    private static $use_default_admin = true;

    private static $username = '';

    public static function get_user_email()
    {
        $userName = Config::inst()->get(CheckAllTemplates::class, 'username');
        if(! $userName) {
            if(Config::inst()->get(CheckAllTemplates::class, 'use_default_admin')) {
                $userName = DefaultAdminService::getDefaultAdminUsername();
            } else {
                $userName = 'smoketest@test.com.ssu';
            }
        }

        return $userName;
    }


    private static $password = '';

    public static function get_password()
    {
        $password = Config::inst()->get(CheckAllTemplates::class, 'password');
        if(! $password) {
            if(Config::inst()->get(CheckAllTemplates::class, 'use_default_admin')) {
                $password = DefaultAdminService::getDefaultAdminPassword();
            } else {
                $cache = Injector::inst()->get(CacheInterface::class . '.templateoverview');
                if(! $cache->has('password')) {
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
        return Injector::inst()->get(CheckAllTemplates::class)->getTestUser();
    }


    private static $segment = 'smoketest';

    /**
     *
     * @inheritdoc
     */
    protected $title = 'Check URLs for HTTP errors';

    /**
     *
     * @inheritdoc
     */
    protected $description = "Will go through main URLs (all page types (e.g Page, MyPageTemplate), all page types in CMS (e.g. edit Page, edit HomePage, new MyPage) and all models being edited in ModelAdmin, checking for HTTP response errors (e.g. 404). Click start to run.";

     private $guzzleCookieJar = null;

     private $guzzleClient = null;

     private $guzzleHasError = false;

    /**
     * temporary Admin used to log in.
     * @var Member
     */
    private $member = null;

    /**
     * @var Boolean
     */
    private $w3validation = false;

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
    public function run($request)
    {
        ini_set('max_execution_time', 3000);
        if ($request->getVar('debugme')) {
            $this->debug = true;
        }
        $asAdmin = $request->getVar('admin') ? true : false;
        $testOne = $request->getVar('test') ? : null;

        //1. actually test a URL and return the data
        if ($testOne) {
            $this->guzzleSetup();
            $this->getTestUser();
            echo $this->testURL($testOne);
            $this->deleteUser();
            $this->cleanup();
        }

        //2. create a list of
        else {
            $count = 0;
            echo "
            <h1><a href=\"#\" class=\"start\">start</a> | <a href=\"#\" class=\"stop\">stop</a></h1>
            <p><strong>Tests Done:</strong> <span id=\"NumberOfTests\">0</span></p>
            <p><strong>Average Response Time:</strong> <span id=\"AverageResponseTime\">0</span></p>
            <table border='1'>
            <tr><th>Link</th><th>HTTP response</th><th>response TIME</th><th class'error'>error</th><th class'error'>W3 Check</th></tr>";
            $array = Injector::inst()->get(AllLinks::class)->getAllLinks();
            $sections = array("allNonAdmins", "allAdmins");
            foreach ($sections as $isAdmin => $sectionVariable) {
                foreach ($array[$sectionVariable] as $link) {
                    $count++;
                    $id = "ID".$count;
                    $linkArray[] = array("IsAdmin" => $isAdmin, "Link" => $link, "ID" => $id);
                    echo "
                        <tr id=\"$id\" class=".($isAdmin ? "isAdmin" : "notAdmin").">
                            <td>
                                <a href=\"$link\" target=\"_blank\">$link</a>
                                <a href=\"".Director::baseURL()."dev/tasks/smoketest/?test=".urlencode($link)."&admin=".$isAdmin."\" style='color: purple' target='_blank'>🔗</a>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    ";
                }
            }
            echo "
            </table>
            <script src='https://code.jquery.com/jquery-3.3.1.min.js'></script>
            <script type='text/javascript'>

                jQuery(document).ready(
                    function(){
                        checker.init();
                    }
                );

                var checker = {

                    useJSTest: false,

                    totalResponseTime: 0,

                    numberOfTests: 0,

                    list: ".Convert::raw2json($linkArray).",

                    baseURL: '/dev/tasks/smoketest/',

                    item: null,

                    stop: true,

                    init: function() {
                        jQuery('a.start').click(
                            function() {
                                checker.stop = false;
                                if(!checker.item) {
                                    checker.item = checker.list.shift();
                                }
                                checker.checkURL();
                            }
                        );
                        jQuery('a.stop').click(
                            function() {
                                checker.stop = true;
                            }
                        );
                        this.initShowMoreClick();
                    },

                    initShowMoreClick: function(){
                        jQuery(\"table\").on(
                            \"click\",
                            \"a.showMoreClick\",
                            function(event){
                                event.preventDefault();
                                jQuery(this).parent().find(\"ul\").slideToggle();
                            }
                        )
                    },

                    checkURL: function(){
                        if(checker.stop) {

                        }
                        else {
                            if(checker.useJSTest) {
                                var data = {};
                                var baseLink = (checker.item.Link);
                            } else {
                                var baseLink = checker.baseURL;
                                var isAdmin = checker.item.IsAdmin;
                                var testLink =  checker.item.Link;
                                var data = {'test': testLink, 'admin': isAdmin}
                            }
                            var ID = checker.item.ID;
                            jQuery('#'+ID).find('td')
                                .css('border', '1px solid blue');
                            jQuery('#'+ID).css('background-image', 'url(/resources/cms/images/loading.gif)')
                                .css('background-repeat', 'no-repeat')
                                .css('background-position', 'top right');
                            jQuery.ajax({
                                url: baseLink,
                                type: 'get',
                                data: data,
                                success: function(data, textStatus){
                                    checker.item = null;
                                    jQuery('#'+ID)
                                        .html(data)
                                        .css('background-image', 'none')
                                        .find('h1').remove();
                                    checker.item = checker.list.shift();
                                    jQuery('#'+ID).find('td').css('border', '1px solid green');
                                    var responseTime = parseFloat(jQuery('#'+ID).find(\"td.tt\").text());
                                    if(responseTime && typeof responseTime !== 'undefined') {
                                        checker.numberOfTests++;
                                        checker.totalResponseTime = checker.totalResponseTime + responseTime;
                                        jQuery(\"#NumberOfTests\").text(checker.numberOfTests);
                                        jQuery(\"#AverageResponseTime\").text(Math.round(100 * (checker.totalResponseTime / checker.numberOfTests)) / 100);
                                    }
                                    window.setTimeout(
                                        function() {checker.checkURL();},
                                        1000
                                    );
                                },
                                error: function(error){
                                    checker.item = null;
                                    jQuery('#'+ID).find('td.error').html('ERROR');
                                    jQuery('#'+ID).css('background-image', 'none');
                                    checker.item = checker.list.shift();
                                    jQuery('#'+ID).find('td').css('border', '1px solid red');
                                    window.setTimeout(
                                        function() {checker.checkURL();},
                                        1000
                                    );
                                },
                                dataType: 'html'
                            });
                        }
                    }
                }
            </script>";
            echo "<h2>Want to add more tests?</h2>
            <p>
                By adding a public method <i>templateoverviewtests</i> to any controller,
                returning an array of links, they will be included in the list above.
            </p>
            ";
            echo "<h3>Suggestions</h3>
            <p>Below is a list of suggested controller links.</p>
            <ul>";
            $className = "";
            foreach ($array['otherLinks'] as $linkArray) {
                if ($linkArray["ClassName"] != $className) {
                    $className = $linkArray["ClassName"];
                    echo "</ul><h2>".$className."</h2><ul>";
                }
                echo "<li><a href=\"".$linkArray["Link"]."\">".$linkArray["Link"]."</a> ".$linkArray["Error"]."</li>";
            }
            echo "</ul>";
        }
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
                    'Authorization' => ['Basic '.$credentials]
                ]
            );
        } catch (RequestException $exception) {
            $this->guzzleHasError = true;
            //echo Psr7\str($exception->getRequest());
            if ($exception->hasResponse()) {
                $response = $exception->getResponseBodySummary($exception->getResponse());
            }
        }
        return $response;
    }



    /**
     *
     */
    public function getTestUser()
    {
        if(Config::inst()->get(CheckAllTemplates::class, 'use_default_admin')) {
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
            if(! $result->isValid()) {
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
        if(Config::inst()->get(CheckAllTemplates::class, 'use_default_admin')) {
            //do nothing;
        } else {
            if ($this->member) {
                $this->member->delete();
            }
        }
    }


    /**
     * removes the temporary user
     * and cleans up the curl connection.
     *
     */
    private function cleanup()
    {

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
        if (strpos($url, "/admin") === 0 || strpos($url, "admin") === 0) {
            $validate = false;
        } else {
            $validate = $this->w3validation;
        }
        $testURL = Director::absoluteURL('/templatesloginandredirect/login/?BackURL=');
        $testURL .= urlencode($url);
        $this->guzzleSetup();

        $start = microtime(true);
        $response = $this->guzzleSendRequest($testURL);
        $end = microtime(true);

        if($this->guzzleHasError) {
            $httpCode = 500;
            $body = '';
            $error = $response;
        } else {
            $httpCode = $response->getStatusCode();
            $body = $response->getBody();
            $error = $response->getReasonPhrase();
        }
        if ($httpCode == "401") {
            echo $url;
            die('COULD NOT ACCESS: '.$url);
        }

        $possibleError = false;

        //uncaught errors ...
        if ($body && substr($body, 0, 12) == "Fatal error") {
            $error = "<span style='color: red;'>$message</span> ";
            $possibleError = true;
        }
        elseif ($body && strlen($body) < 2000) {
            $error = "<span style='color: red;'>SHORT RESPONSE: $body</span> ";
            $possibleError = true;
        }

        $html = '';
        if($httpCode == 200) {
            if($possibleError) {
                $html .= "<td style='color:red'><a href='$url' style='color: red!important; text-decoration: none;'>$url</a></td>";
            } else {
                $html .= "<td style='color:green'><a href='$url' style='color: grey!important; text-decoration: none;' target='_blank'>$url</a></td>";
            }
        } else {
            $error .= "unexpected response: ".$error;
            $html .= "<td style='color:red'><a href='$url' style='color: red!important; text-decoration: none;'>$url</a></td>";
        }
        $timeTaken = round($end - $start, 4);
        $html .= "<td style='text-align: right'>$httpCode</td><td style='text-align: right' class=\"tt\">$timeTaken</td><td>$error</td>";

        if ($validate && $httpCode == 200) {
            $w3Obj = new W3cValidateApi();
            $html .= "<td>".$w3Obj->W3Validate("", $body)."</td>";
        } else {
            $html .= "<td>n/a</td>";
        }

        return $html;
    }


    private function debugme($lineNumber, $variable ="")
    {
        if ($this->debug) {
            echo "<br />".$lineNumber .": ".round(memory_get_usage() / 1048576)."MB"."=====".print_r($variable, 1);
            ob_flush();
            flush();
        }
    }




}
