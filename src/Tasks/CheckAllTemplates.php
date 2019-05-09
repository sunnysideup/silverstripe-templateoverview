<?php

namespace Sunnysideup\TemplateOverview\Tasks;

use Sunnysideup\TemplateOverview\Api\TemplateOverviewPageAPI;
use Sunnysideup\TemplateOverview\Api\W3cValidateApi;

use ReflectionClass;
use ReflectionMethod;

use SilverStripe\Control\Director;
use SilverStripe\Core\Convert;
use SilverStripe\Security\Member;
use SilverStripe\Security\Group;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
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



/**
 * @description (see $this->description)
 *
 * @authors: Andrew Pett [at] sunny side up .co.nz, Nicolaas [at] Sunny Side Up .co.nz
 * @package: templateoverview
 * @sub-package: tasks
 **/

class CheckAllTemplates extends BuildTask
{


    /**
     * @var Array
     * all of the admin acessible links
     */
    private static $custom_links = [];

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

    /**
      * List of URLs to be checked. Excludes front end pages (Cart pages etc).
      */
    private $modelAdmins = [];

    /**
     * @var Array
     * all of the public acessible links
     */
    private $allOpenLinks = [];

    /**
     * @var Array
     * all of the admin acessible links
     */
    private $allAdmins = [];

    /**
     * @var Array
     * Pages to check by class name. For example, for "ClassPage", will check the first instance of the cart page.
     */
    private $classNames = [];

    /**
     *
     * @var curlHolder
     */
    private $ch = null;

    /**
     * temporary Admin used to log in.
     * @var Member
     */
    private $member = null;

    /**
     * temporary username for temporary admin
     * @var String
     */
    private $username = "";

    /**
     * temporary password for temporary admin
     * @var String
     */
    private $password = "";

    /**
     * @var Boolean
     */
    private $w3validation = true;

    /**
     * @var Boolean
     */
    private $debug = false;

    /**
     * this variable can help with situations where there are
     * unfixable bugs in Live and you want to run the tests
     * on Draft instead... (or vice versa)
     * @var String (Live or '')
     */
    private $stage = '';

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
        if (isset($_GET["debugme"])) {
            $this->debug = true;
        }
        $asAdmin = empty($_REQUEST["admin"]) ? false : true;
        $testOne = isset($_REQUEST["test"]) ? $_GET["test"] : null;

        //1. actually test a URL and return the data
        if ($testOne) {
            $this->setupCurl();
            if ($asAdmin) {
                $this->createAndLoginUser();
            }
            echo $this->testURL($testOne, $this->w3validation);
            $this->cleanup();
        }

        //2. create a list of
        else {
            foreach($this->Config()->get('custom_links') as $link) {
                $link = '/'.ltrim($link, '/').'/';
                if(substr($link,  0 , 6) === '/admin') {
                    $this->customLinksAdmin[] = $link;
                } else {
                    $this->customLinksNonAdmin[] = $link;
                }
            }
            $this->classNames = $this->listOfAllClasses();
            $this->allNonAdmins = $this->prepareClasses();
            $this->allAdmins = $this->array_push_array($this->allAdmins, $this->customLinksNonAdmin);


            $this->modelAdmins = $this->ListOfAllModelAdmins();
            $this->allAdmins = $this->array_push_array($this->modelAdmins, $this->prepareClasses(1));
            $this->allAdmins = $this->array_push_array($this->allAdmins, $this->customLinksAdmin);

            $otherLinks = $this->listOfAllControllerMethods();
            $sections = array("allNonAdmins", "allAdmins");
            $count = 0;
            echo "
            <h1><a href=\"#\" class=\"start\">start</a> | <a href=\"#\" class=\"stop\">stop</a></h1>
            <p><strong>Tests Done:</strong> <span id=\"NumberOfTests\">0</span></p>
            <p><strong>Average Response Time:</strong> <span id=\"AverageResponseTime\">0</span></p>
            <table border='1'>
            <tr><th>Link</th><th>HTTP response</th><th>response TIME</th><th class'error'>error</th><th class'error'>W3 Check</th></tr>";
            foreach ($sections as $isAdmin => $sectionVariable) {
                foreach ($this->$sectionVariable as $link) {
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
                            var testLink = (checker.item.Link);
                            var isAdmin = checker.item.IsAdmin;
                            var ID = checker.item.ID;
                            jQuery('#'+ID).find('td')
                                .css('border', '1px solid blue');
                            jQuery('#'+ID).css('background-image', 'url(/cms/images/loading.gif)')
                                .css('background-repeat', 'no-repeat')
                                .css('background-position', 'top right');
                            jQuery.ajax({
                                url: checker.baseURL,
                                type: 'get',
                                data: {'test': testLink, 'admin': isAdmin},
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
                                error: function(){
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
            foreach ($otherLinks as $linkArray) {
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
    private function setupCurl($type = "GET")
    {
        $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
        $post = $type == "GET" ? false : true;

        $strCookie = 'PHPSESSID=' . session_id() . '; path=/';
        $options = array(
            CURLOPT_CUSTOMREQUEST  => $type,        //set request type post or get
            CURLOPT_POST           => $post,        //set to GET
            CURLOPT_USERAGENT      => $user_agent, //set user agent
            CURLOPT_COOKIE         => $strCookie, //set cookie file
            CURLOPT_COOKIEFILE     => "cookie.txt", //set cookie file
            CURLOPT_COOKIEJAR      => "cookie.txt", //set cookie jar
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER         => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING       => "",       // handle all encodings
            CURLOPT_AUTOREFERER    => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT        => 120,      // timeout on response
            CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
        );

        $this->ch = curl_init();

        curl_setopt_array($this->ch, $options);
    }



    /**
     * creates and logs in a temporary user.
     *
     */
    private function createAndLoginUser()
    {
        $this->username = "TEMPLATEOVERVIEW_URLCHECKER___";
        $this->password = rand(1000000000, 9999999999).'#KSADRFEddweed';
        //Make temporary admin member
        $adminMember = Member::get()->filter(array("Email" => $this->username))->limit(1)->first();
        if ($adminMember != null) {
            $adminMember->delete();
        }
        $this->member = new Member();
        $this->member->Email = $this->username;
        $this->member->Password = $this->password;
        $this->member->write();
        $adminGroup = Group::get()->filter(array("code" => "administrators"))->limit(1)->first();
        if (!$adminGroup) {
            user_error("No admin group exists");
        }
        $this->member->Groups()->add($adminGroup);

        curl_setopt($this->ch, CURLOPT_USERPWD, $this->username.":".$this->password);

        $loginUrl = Director::absoluteURL('/Security/LoginForm');
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($this->ch, CURLOPT_URL, $loginUrl);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, 'Email='.$this->username.'&Password='.$this->password);


        //execute the request (the login)
        $loginContent      = curl_exec($this->ch);
        $httpCode          = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $err               = curl_errno($this->ch);
        $errmsg            = curl_error($this->ch);
        $header            = curl_getinfo($this->ch);
        if ($httpCode != 200) {
            echo "<span style='color:red'>There was an error logging in!</span><br />";
        }
    }

    /**
     * removes the temporary user
     * and cleans up the curl connection.
     *
     */
    private function cleanup()
    {
        if ($this->member) {
            $this->member->delete();
        }
        curl_close($this->ch);
    }

    /**
      * Takes an array, takes one item out, and returns new array
      * @param Array $array Array which will have an item taken out of it.
      * @param - $exclusion Item to be taken out of $array
      * @return Array New array.
      */
    private function arrayExcept($array, $exclusion)
    {
        $newArray = $array;
        for ($i = 0; $i < count($newArray); $i++) {
            if ($newArray[$i] == $exclusion) {
                unset($newArray[$i]);
            }
        }
        return $newArray;
    }

    /**
     * ECHOES the result of testing the URL....
     * @param String $url
     */
    private function testURL($url, $validate = true)
    {
        if (strlen(trim($url)) < 1) {
            user_error("empty url"); //Checks for empty strings.
        }
        if (strpos($url, "/admin") === 0 || strpos($url, "admin") === 0) {
            $validate = false;
        }

        $url = Director::absoluteURL($url);
        //start basic CURL
        curl_setopt($this->ch, CURLOPT_URL, $url);

        $response          = curl_exec($this->ch);

        $httpCode          = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        if ($httpCode == "401") {
            echo $url;
            // $this->createAndLoginUser();
            // return $this->testURL($url, false);

            // Build request and detect flush
            $variables = [
                '_SERVER' => [],
                '_GET' => [],
                '_POST' => [],
            ];
            $variables['_SERVER']['REQUEST_METHOD'] = 'get';
            $input = '';
            // $variables['_SESSION'] = Session::get();
            $request = HTTPRequestBuilder::createFromVariables(
                $variables,
                $input,
                $url
            );

            // Default application
            $kernel = new CoreKernel(BASE_PATH);
            $app = new HTTPApplication($kernel);
            $app->addMiddleware(new ErrorControlChainMiddleware($app));
            $response = $app->handle($request);
            $response->output();


            // Test application
            $kernel = new TestKernel(BASE_PATH);
            $app = new HTTPApplication($kernel);

            $request = CLIRequestBuilder::createFromEnvironment();
            // Custom application
            $app->execute($request, function (HTTPRequest $request) {
                // Start session and execute
                $request->getSession()->init($request);

                // Invalidate classname spec since the test manifest will now pull out new subclasses for each internal class
                // (e.g. Member will now have various subclasses of DataObjects that implement TestOnly)
                DataObject::reset();

                // Set dummy controller;
                $controller = Controller::create();
                $controller->setRequest($request);
                $controller->pushCurrent();
                $controller->doInit();
            }, false);

        }
        //get more curl!

        $err               = curl_errno($this->ch);
        $errmsg            = curl_error($this->ch);
        $header            = curl_getinfo($this->ch);
        $length            = curl_getinfo($this->ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $timeTaken         = curl_getinfo($this->ch, CURLINFO_TOTAL_TIME);

        $timeTaken = number_format((float)$timeTaken, 2, '.', '');
        $possibleError = false;
        $error = "none";
        if (substr($response, 0, 12) == "Fatal error") {
            $error = "<span style='color: red;'>$response</span> ";
            $possibleError = true;
        }
        if (strlen($response) < 2000) {
            $error = "<span style='color: red;'>$response</span> ";
            $possibleError = true;
        }

        $html = "";
        if ($httpCode == 200 && !$possibleError) {
            $html .= "<td style='color:green'><a href='$url' style='color: grey!important; text-decoration: none;' target='_blank'>$url</a></td>";
        } else {
            if (!$possibleError) {
                $error = "unexpected response";
            }
            $html .= "<td style='color:red'><a href='$url' style='color: red!important; text-decoration: none;'>$url</a></td>";
        }
        $html .= "<td style='text-align: right'>$httpCode</td><td style='text-align: right' class=\"tt\">$timeTaken</td><td>$error</td>";

        if ($validate && $httpCode == 200) {
            $w3Obj = new W3cValidateApi();
            $html .= "<td>".$w3Obj->W3Validate("", $response)."</td>";
        } else {
            $html .= "<td>n/a</td>";
        }
        return $html;
    }

    /**
      * Pushes an array of items to an array
      * @param Array $array Array to push items to (will overwrite)
      * @param Array $pushArray Array of items to push to $array.
      */
    private function array_push_array($array, $pushArray)
    {
        foreach ($pushArray as $pushItem) {
            if(! in_array($pushItem, $array)) {
                array_push($array, $pushItem);
            }
        }
        return $array;
    }

    /**
     * returns a list of all SiteTree Classes
     * @return Array(String)
     */
    private function listOfAllClasses()
    {
        $pages = [];
        $list = null;
        if (class_exists(TemplateOverviewPageAPI::class)) {
            $templateOverviewPageAPI = Injector::inst()->get(TemplateOverviewPageAPI::class);
            $list = $templateOverviewPageAPI->ListOfAllClasses();
            foreach ($list as $page) {
                $pages[] = $page->ClassName;
            }
        }
        if (!count($pages)) {
            $list = ClassInfo::subclassesFor(SiteTree::class);
            foreach ($list as $page) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * returns a list of all model admin links
     * @return Array(String)
     */
    private function ListOfAllModelAdmins()
    {
        $models = [];
        $modelAdmins = CMSMenu::get_cms_classes(ModelAdmin::class);
        if ($modelAdmins && count($modelAdmins)) {
            foreach ($modelAdmins as $modelAdmin) {
                $obj = singleton($modelAdmin);
                $modelAdminLink = '/'.$obj->Link();
                $modelAdminLinkArray = explode("?", $modelAdminLink);
                $modelAdminLink = $modelAdminLinkArray[0];
                //$extraVariablesLink = $modelAdminLinkArray[1];
                $models[] = $modelAdminLink;
                $modelsToAdd = $obj->getManagedModels();

                if ($modelsToAdd && count($modelsToAdd)) {
                    foreach ($modelsToAdd as $key => $model) {
                        if (is_array($model) || !is_subclass_of($model, DataObject::class)) {
                            $model = $key;
                        }
                        if (!is_subclass_of($model, DataObject::class)) {
                            continue;
                        }
                        $modelAdminLink;
                        $modelLink = $modelAdminLink.$this->sanitiseClassName($model)."/";
                        $models[] = $modelLink;
                        $models[] = $modelLink."EditForm/field/".$this->sanitiseClassName($model)."/item/new/";
                        $item = $model::get()
                            ->sort(DB::get_conn()->random().' ASC')
                            ->First();
                        if ($item) {
                            $models[] = $modelLink."EditForm/field/".$this->sanitiseClassName($model)."/item/".$item->ID."/edit";
                        }
                    }
                }
            }
        }

        return $models;
    }

    protected function listOfAllControllerMethods()
    {
        $array = [];
        $finalArray = [];
        $classes = ClassInfo::subclassesFor(Controller::class);
        //foreach($manifest as $class => $compareFilePath) {
        //if(stripos($compareFilePath, $absFolderPath) === 0) $matchedClasses[] = $class;
        //}
        $manifest = ClassLoader::inst()->getManifest()->getClasses();
        $baseFolder = Director::baseFolder();
        $cmsBaseFolder = Director::baseFolder()."/cms/";
        $frameworkBaseFolder = Director::baseFolder()."/framework/";
        if (Director::isDev()) {
            foreach ($classes as $className) {
                $lowerClassName = strtolower($className);
                $location = $manifest[$lowerClassName];
                if (strpos($location, $cmsBaseFolder) === 0 || strpos($location, $frameworkBaseFolder) === 0) {
                    continue;
                }
                if ($className != Controller::class) {
                    $controllerReflectionClass = new ReflectionClass($className);
                    if (!$controllerReflectionClass->isAbstract()) {
                        if (
                            $className == "HideMailto" ||
                            $className == "HideMailtoController" ||
                            $className == "Mailto" ||
                            $className instanceof SapphireTest ||
                            $className instanceof BuildTask ||
                            $className instanceof TaskRunner
                        ) {
                            continue;
                        }
                        $methods = $this->getPublicMethodsNotInherited($controllerReflectionClass, $className);
                        foreach ($methods as $methodArray) {
                            $array[$className."_".$methodArray["Method"]] = $methodArray;
                        }
                    }
                }
            }
            $finalArray = [];
            $doubleLinks = [];
            foreach ($array as $index  => $classNameMethodArray) {
                if(1 === 2) {
                    try {
                        $classObject = @Injector::inst()->get($classNameMethodArray["ClassName"]);
                        if($classObject) {
                            if(Config::inst()->get($classNameMethodArray["ClassName"], 'url_segment')) {
                                if ($classNameMethodArray["Method"] == "templateoverviewtests") {
                                    $this->customLinks = array_merge($classObject->templateoverviewtests(), $this->customLinks);
                                } else {
                                    $link = $classObject->Link($classNameMethodArray["Method"]);
                                    if ($link == $classNameMethodArray["ClassName"]."/") {
                                        $link = $classNameMethodArray["ClassName"]."/".$classNameMethodArray["Method"]."/";
                                    }
                                    $classNameMethodArray["Link"] = $link;
                                    if ($classNameMethodArray["Link"][0] != "/") {
                                        $classNameMethodArray["Link"] = Director::baseURL().$classNameMethodArray["Link"];
                                    }
                                    if (!isset($doubleLinks[$link])) {
                                        $finalArray[] = $classNameMethodArray;
                                    }
                                    $doubleLinks[$link] = true;
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // echo 'Caught exception: ',  $e->getMessage(), "\n";
                    }
                }
            }
        }
        return $finalArray;
    }



    private function getPublicMethodsNotInherited($classReflection, $className)
    {
        $classMethods = $classReflection->getMethods();
        $classMethodNames = [];
        foreach ($classMethods as $index => $method) {
            if ($method->getDeclaringClass()->getName() !== $className) {
                unset($classMethods[$index]);
            } else {
                $allowedActionsArray = Config::inst()->get($className, "allowed_actions", Config::UNINHERITED);
                if (!is_array($allowedActionsArray)) {
                    $allowedActionsArray = [];
                } else {
                    //return $allowedActionsArray;
                }
                $methodName = $method->getName();
                /* Get a reflection object for the class method */
                $reflect = new ReflectionMethod($className, $methodName);
                /* For private, use isPrivate().  For protected, use isProtected() */
                /* See the Reflection API documentation for more definitions */
                if ($reflect->isPublic()) {
                    if ($methodName == strtolower($methodName)) {
                        if (strpos($methodName, "_") == null) {
                            if (!in_array($methodName, array("index", "run", "init"))) {
                                /* The method is one we're looking for, push it onto the return array */
                                $error = "";
                                if (!in_array($methodName, $allowedActionsArray) && !isset($allowedActionsArray[$methodName])) {
                                    $error = "Can not find ".$className."::".$methodName." in allowed_actions";
                                } else {
                                    unset($allowedActionsArray[$className]);
                                }
                                $classMethodNames[$methodName] = array(
                                    "ClassName" => $className,
                                    "Method" => $methodName,
                                    "Error" => $error
                                );
                            }
                        }
                    }
                }
                if (count($allowedActionsArray)) {
                    $classSpecificAllowedActionsArray = Config::inst()->get($className, "allowed_actions", Config::UNINHERITED);
                    if (is_array($classSpecificAllowedActionsArray) && count($classSpecificAllowedActionsArray)) {
                        foreach ($allowedActionsArray as $methodName => $methodNameWithoutKey) {
                            if (is_numeric($methodName)) {
                                $methodName = $methodNameWithoutKey;
                            }
                            if (isset($classSpecificAllowedActionsArray[$methodName])) {
                                $classMethodNames[$methodName] = array(
                                    "ClassName" => $className,
                                    "Method" => $methodName,
                                    "Error" => "May not follow the right method name formatting (all lower case)"
                                );
                            }
                        }
                    }
                }
            }
        }
        return $classMethodNames;
    }

    /**
     * Takes {@link #$classNames}, gets the URL of the first instance of it
     * (will exclude extensions of the class) and
     * appends to the {@link #$urls} list to be checked
     *
     * @param Boolean $pageInCMS
     *
     * @return Array(String)
     */
    private function prepareClasses($pageInCMS = false)
    {
        //first() will return null or the object
        $return = [];
        foreach ($this->classNames as $class) {
            $this->debugme(__LINE__, $class);
            $excludedClasses = $this->arrayExcept($this->classNames, $class);
            if ($pageInCMS) {
                $stage = "";
            } else {
                $stage = $this->stage;
            }
            $page = Versioned::get_by_stage($class, Versioned::DRAFT)
                ->exclude(array("ClassName" => $excludedClasses))
                ->sort(DB::get_conn()->random().' ASC')
                ->limit(1);
            $page = $page->first();
            if ($page) {
                if ($pageInCMS) {
                    $url = $page->CMSEditLink();
                } else {
                    $url = $page->Link();
                }
                $return[] = $url;
            }
        }
        return $return;
    }


    private function debugme($lineNumber, $variable ="")
    {
        if ($this->debug) {
            echo "<br />".$lineNumber .": ".round(memory_get_usage() / 1048576)."MB"."=====".print_r($variable, 1);
            ob_flush();
            flush();
        }
    }


    /**
     * Sanitise a model class' name for inclusion in a link
     *
     * @param string $class
     * @return string
     */
    protected function sanitiseClassName($class)
    {
        return str_replace('\\', '-', $class);
    }

}
