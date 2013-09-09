<?php

/**
 * @description (see $this->description)
 *
 * @authors: Andrew Pett [at] sunny side up .co.nz, Nicolaas [at] Sunny Side Up .co.nz
 * @package: templateoverview
 * @sub-package: tasks
 **/

class CheckAllTemplates extends BuildTask {

	protected $title = 'Check URLs for HTTP errors';

	protected $description = "Will go through main URLs (all page types (e.g Page, MyPageTemplate), all page types in CMS (e.g. edit Page, edit HomePage, new MyPage) and all models being edited in ModelAdmin, checking for HTTP response errors (e.g. 404). Click start to run.";

	/**
	  * List of URLs to be checked. Excludes front end pages (Cart pages etc).
	  */
	private $modelAdmins = array();

	/**
	 * @var Array
	 * all of the public acessible links
	 */
	private $allOpenLinks = array();

	/**
	 * @var Array
	 * all of the admin acessible links
	 */
	private $allAdmins = array();

	/**
	 * @var Array
	 * all of the admin acessible links
	 */
	private $customLinks = array();

	/**
	 * @var Array
	 * Pages to check by class name. For example, for "ClassPage", will check the first instance of the cart page.
	 */
	private $classNames = array();

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
	private $w3validation = false;

	/**
	 * Main function
	 * has two streams:
	 * 1. check on url specified in GET variable.
	 * 2. create a list of urls to check
	 *
	 */
	public function run($request) {
		$asAdmin = empty($_REQUEST["admin"]) ? false : true;
		$testOne = isset($_REQUEST["test"]) ? $_GET["test"] : null;

		//1. actually test a URL and return the data
		if($testOne) {
			$this->setupCurl();
			if($asAdmin) {
				$this->createAndLoginUser();
			}
			echo $this->testURL($testOne, $this->w3validation);
			$this->cleanup();
		}

		//2. create a list of
		else {
			Requirements::javascript(THIRDPARTY_DIR . '//ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.min.js');
			$this->classNames = $this->listOfAllClasses();
			$this->modelAdmins = $this->ListOfAllModelAdmins();
			$this->allNonAdmins = $this->prepareClasses();
			$otherLinks = $this->listOfAllControllerMethods();
			$this->allAdmins = $this->array_push_array($this->modelAdmins, $this->prepareClasses(1));
			$this->allAdmins = $this->array_push_array($this->allAdmins, $this->customLinks);
			$sections = array("allNonAdmins", "allAdmins");
			$count = 0;
			echo "<h1><a href=\"#\" class=\"start\">start</a> | <a href=\"#\" class=\"stop\">stop</a></h1>
			<table border='1'>
			<tr><th>Link</th><th>HTTP response</th><th>response TIME</th><th class'error'>error</th><th class'error'>W3 Check</th></tr>";
			foreach($sections as $isAdmin => $section) {
				foreach($this->$section as $link) {
					$count++;
					$id = "ID".$count;
					$linkArray[] = array("IsAdmin" => $isAdmin, "Link" => $link, "ID" => $id);
					echo "
						<tr id=\"$id\" class=".($isAdmin ? "isAdmin" : "notAdmin").">
							<td><a href=\"/dev/tasks/CheckAllTemplates/?test=".urlencode($link)."&admin=".$isAdmin."\" style='color: purple' target='_blank'>$link</a></td>
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
			<script src='http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js' ></script>
			<script type='text/javascript'>

				jQuery(document).ready(
					function(){
						checker.init();
					}
				);

				var checker = {
					list: ".Convert::raw2json($linkArray).",

					baseURL: '/dev/tasks/CheckAllTemplates/',

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
									jQuery('#'+ID).html(data).css('background-image', 'none');
									jQuery('#'+ID).find('h1').remove();
									checker.item = checker.list.shift();
									jQuery('#'+ID).find('td').css('border', '1px solid green');

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
			foreach($otherLinks as $link) {
				echo "<li><a href=\"$link\">$link</a></li>";
			}
			echo "</ul>";
		}
	}

	/**
	 * creates the basic curl
	 *
	 */
	private function setupCurl(){
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, TRUE);
	}


	/**
	 * creates and logs in a temporary user.
	 *
	 */
	private function createAndLoginUser(){
		$this->username = "TEMPLATEOVERVIEW_URLCHECKER___";
		$this->password = rand(1000000000,9999999999);
		//Make temporary admin member
		$adminMember = Member::get()->filter(array("Email" => $this->username))->first();
		if($adminMember != NULL) {
			$adminMember->delete();
		}
		$this->member = new Member();
		$this->member->Email = $this->username;
		$this->member->Password = $this->password;
		$this->member->write();
		$adminGroup = Group::get()->filter(array("code" => "administrators"))->first();
		if(!$adminGroup) {
			user_error("No admin group exists");
		}
		$this->member->Groups()->add($adminGroup);
		curl_setopt($this->ch, CURLOPT_USERPWD, $this->username.":".$this->password);

		$loginUrl = Director::absoluteURL('/Security/LoginForm');
		curl_setopt($this->ch, CURLOPT_URL, $loginUrl);
		curl_setopt($this->ch, CURLOPT_POST, 1);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, 'Email='.$this->username.'&Password='.$this->password);
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, 'cookie.txt');


		//execute the request (the login)
		$loginContent = curl_exec($this->ch);
		$httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		if($httpCode != 200) {
			echo "<span style='color:red'>There was an error logging in!</span><br />";
		}
	}

	/**
	 * removes the temporary user
	 * and cleans up the curl connection.
	 *
	 */
	private function cleanup(){
		if($this->member) {
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
	private function arrayExcept($array, $exclusion) {
		$newArray = $array;
		for($i = 0; $i < count($newArray); $i++) {
			if($newArray[$i] == $exclusion) unset($newArray[$i]);
		}
		return $newArray;
	}

	/**
	 * ECHOES the result of testing the URL....
	 * @param String $url
	 */
	private function testURL($url, $validate = true) {
		if(strlen(trim($url)) < 1) {
			user_error("empty url"); //Checks for empty strings.
		}

		$url = Director::absoluteURL($url);

		curl_setopt($this->ch, CURLOPT_URL, $url);
		$response = curl_exec($this->ch);
		$httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		if($httpCode == "401") {
			$this->createAndLoginUser();
			return $this->testURL($url, false);
		}
		$timeTaken = curl_getinfo($this->ch, CURLINFO_TOTAL_TIME);
		$timeTaken = number_format((float)$timeTaken, 2, '.', '');
		$length = curl_getinfo($this->ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		$possibleError = false;
		if((strlen($response) < 500) || ($length < 500) || (substr($response, 0, 11) == "Fatal error")) {
			$error = "<span style='color: red;'>short response / error response</span> ";
		}
		$error = "none";
		$html = "";
		if($httpCode == 200 ) {
			$html .= "<td style='color:green'><a href='$url' style='color: grey!important; text-decoration: none;'>$url</a></td>";
		}
		else {
			$error = "unexpected response";
			$html .= "<td style='color:red'><a href='$url' style='color: red!important; text-decoration: none;'>$url</a></td>";
		}
		$html .= "<td style='text-align: right'>$httpCode</td><td style='text-align: right'>$timeTaken</td><td>$error</td>";

		if($validate) {
			$w3Obj = new CheckAllTemplates_W3cValidateApi();
			$html .= "<td>".$w3Obj->W3Validate($url)."</td>";
		}
		else {
			$html .= "<td>turned off</td>";
		}
		return $html;
	}

	/**
	  * Pushes an array of items to an array
	  * @param Array $array Array to push items to (will overwrite)
	  * @param Array $pushArray Array of items to push to $array.
	  */
	private function array_push_array($array, $pushArray) {
		foreach($pushArray as $pushItem) {
			array_push($array, $pushItem);
		}
		return $array;
	}

	/**
	 * returns a lis of all SiteTree Classes
	 * @return Array(String)
	 */
	private function listOfAllClasses(){
		$pages = array();
		$list = null;
		if(class_exists("TemplateOverviewPage")) {
			$templateOverviewPage = TemplateOverviewPage::get()->First();
			if(!$templateOverviewPage) {
				$templateOverviewPage = singleton("TemplateOverviewPage");
			}
			$list = $templateOverviewPage->ListOfAllClasses();
			foreach($list as $page) {
				$pages[] = $page->ClassName;
			}
		}
		if(!count($pages)) {
			$list = ClassInfo::subclassesFor("SiteTree");
			foreach($list as $page) {
				$pages[] = $page;
			}
		}
		return $pages;
	}

	/**
	 * returns a list of all model admin links
	 * @return Array(String)
	 */
	private function ListOfAllModelAdmins(){
		$models = array();
		$modelAdmins = CMSMenu::get_cms_classes("ModelAdmin");
		if($modelAdmins && count($modelAdmins)) {
			foreach($modelAdmins as $modelAdmin) {
				if($modelAdmin != "ModelAdminEcommerceBaseClass") {
					$obj = singleton($modelAdmin);
					$modelAdminLink = $obj->Link();
					$models[] = $modelAdminLink;
					$modelsToAdd = $obj->getManagedModels();
					if($modelsToAdd && count($modelsToAdd)) {
						foreach($modelsToAdd as $key => $model) {
							if(is_array($model) || !is_subclass_of($model, "DataObject")) {
								$model = $key;
							}
							if(!is_subclass_of($model, "DataObject")) {
								continue;
							}
							$modelLink = $modelAdminLink.$model."/";
							$models[] = $modelLink;
							$models[] = $modelLink."EditForm/field/".$model."/item/new/";
							if($item = $model::get()->First()) {
								$models[] = $modelLink."EditForm/field/".$model."/item/".$item->ID."/edit";
							}
						}
					}
				}
			}
		}
		return $models;
	}

	protected function  listOfAllControllerMethods(){
		$array = array();
		$classes = ClassInfo::subclassesFor("Controller");
		//foreach($manifest as $class => $compareFilePath) {
			//if(stripos($compareFilePath, $absFolderPath) === 0) $matchedClasses[] = $class;
		//}
		$manifest = SS_ClassLoader::instance()->getManifest()->getClasses();
		$baseFolder = Director::baseFolder();
		$cmsBaseFolder = Director::baseFolder()."/cms/";
		$frameworkBaseFolder = Director::baseFolder()."/framework/";
		foreach($classes as $className) {
			$lowerClassName = strtolower($className);
			$location = $manifest[$lowerClassName];
			if(strpos($location, $cmsBaseFolder) === 0 || strpos($location, $frameworkBaseFolder) === 0) {
				continue;
			}
			if($className != "Controller") {
				$controllerReflectionClass = new ReflectionClass($className);
				if(!$controllerReflectionClass->isAbstract()) {
					if($className instanceOF SapphireTest ||
					   $className instanceOF BuildTask ||
					   $className instanceOF TaskRunner) {
						continue;
					}
					$methods = $this->getPublicMethodsNotInherited($className);
					foreach($methods as $method){
						if($method == strtolower($method)) {
							if(strpos($method, "_") == NULL) {
								if(!in_array($method, array("index", "run", "init"))) {
									$array[$className."_".$method] = array($className, $method);
								}
							}
						}
					}
				}
			}
		}
		$finalArray = array();
		$doubleLinks = array();
		foreach($array as $index  => $classNameMethodArray) {
			if(stripos($classNameMethodArray[0], "Mailto") == NULL) {
				//ob_flush();
				//flush();
				$classObject = singleton($classNameMethodArray[0]);
				if($classNameMethodArray[1] == "templateoverviewtests") {
					$this->customLinks = array_merge($classObject->templateoverviewtests(), $this->customLinks);
				}
				else {
					$link = Director::absoluteURL($classObject->Link($classNameMethodArray[1]));
					if(!isset($doubleLinks[$link])) {
						$finalArray[$index] = $link;
					}
					$doubleLinks[$link] = true;
				}
			}
		}
		return $finalArray;
	}

	private function getPublicMethodsNotInherited($className) {
		$classReflection = new ReflectionClass($className);
		$classMethods = $classReflection->getMethods();
		$classMethodNames = array();
		foreach ($classMethods as $index => $method) {
			if ($method->getDeclaringClass()->getName() !== $className) {
			 unset($classMethods[$index]);
			}
			else {
				/* Get a reflection object for the class method */
				$reflect = new ReflectionMethod($className, $method->getName());
				/* For private, use isPrivate().  For protected, use isProtected() */
				/* See the Reflection API documentation for more definitions */
				if($method->isPublic()) {
						/* The method is one we're looking for, push it onto the return array */
					$classMethodNames[] = $method->getName();
				}
			}
		}
		return $classMethodNames;
	}

	/**
	 * Takes {@link #$classNames}, gets the URL of the first instance of it (will exclude extensions of the class) and
	 * appends to the {@link #$urls} list to be checked
	 * @return Array(String)
	 */
	private function prepareClasses($publicOrAdmin = 0) {
		//first() will return null or the object
		$return = array();
		foreach($this->classNames as $class) {
			$page = $class::get()->exclude(array("ClassName" => $this->arrayExcept($this->classNames, $class)))->first();
			if($page) {
				if($publicOrAdmin) {
					$url = "/admin/pages/edit/show/".$page->ID;
				}
				else {
					$url = $page->link();
				}
				$return[] = $url;
			}
		}
		return $return;
	}



}


/*
   Author:	Jamie Telin (jamie.telin@gmail.com), currently at employed Zebramedia.se

   Scriptname: W3C Validation Api v1.0 (W3C Markup Validation Service)

   Use:
   		//Create new object
			$validate = new W3cValidateApi;

			//Example 1
				$validate->setUri('http://google.com/');	//Set URL to check
				echo $validate->makeValidationCall();		//Will return SOAP 1.2 response

			//Example 2
				$a = $validate->validate('http://google.com/');
				if($a){
					echo 'Verified!';
				} else {
					echo 'Not verified!<br>';
					echo 'Errors found: ' . $validate->ValidErrors;
				}

			//Example 3
				$validate->ui_validate('http://google.com/'); //Visual display

			//Settings
				$validate->Output 		//Set the type of output you want, default = soap12 or web
				$validate->Uri 			//Set url to be checked
				$validate->setUri($uri) //Set url to be checked and make callUrl, deafault way to set URL
				$validate->SilentUi		//Set to false to prevent echo the vidual display
				$validate->Sleep		//Default sleeptime is 1 sec after API call
*/

class CheckAllTemplates_W3cValidateApi{

	private $BaseUrl = 'http://validator.w3.org/check';
	private $Output = 'soap12';
	private $Uri = '';
	private $Feedback;
	private $CallUrl = '';
	private $ValidResult = false;
	private $ValidErrors = 0;
	private $SilentUi = false;
	private $Ui = '';

	private function W3cValidateApi(){
		//Nothing...
	}

	private function makeCallUrl(){
		$this->CallUrl = $this->BaseUrl . "?output=" . $this->Output . "&uri=" . $this->Uri;
	}

	private function setUri($uri){
		$this->Uri = $uri;
		$this->makeCallUrl();
	}

	private function makeValidationCall(){
		if($this->CallUrl != '' && $this->Uri != '' && $this->Output != ''){
			$handle = fopen($this->CallUrl, "rb");
			$contents = '';
			while (!feof($handle)) {
				$contents .= fread($handle, 8192);
			}
			fclose($handle);
			$this->Feedback = $contents;
			return $contents;
		}
		else {
			return false;
		}
	}

	private function validate($uri){
		if($uri != ''){
			$this->setUri($uri);
		} else {
			$this->makeCallUrl();
		}

		$this->makeValidationCall();

		$a = strpos($this->Feedback, '<m:validity>', 0)+12;
		$b = strpos($this->Feedback, '</m:validity>', $a);
		$result = substr($this->Feedback, $a, $b-$a);
		if($result == 'true'){
			$result = true;
		}
		else {
			$result = false;
		}
		$this->ValidResult = $result;

		if($result){
			return $result;
		}
		else {
			//<m:errorcount>3</m:errorcount>
			$a = strpos($this->Feedback, '<m:errorcount>', $a)+14;
			$b = strpos($this->Feedback, '</m:errorcount>', $a);
			$errors = substr($this->Feedback, $a, $b-$a);
			$this->ValidErrors = $errors;
		}
	}

	public function W3Validate($uri){
		if(!$this->isPublicURL($uri)){
			$msg1 = 'NOT A PUBLIC URL';
			$color1 = '#ccc';
			$this->ValidErrors = "";
		}
		else {
			$this->validate($uri);
			if($this->ValidResult){
				$msg1 = 'PASS';
				$color1 = '#00CC00';
			}
			else {
				$msg1 = 'FAIL';
				$color1 = '#FF3300';
			}
		}
		$ui = '<div style="background:'.$color1.';"><strong>'.$msg1.'</strong>'.$this->ValidErrors.'</div>';
		$this->Ui = $ui;
		return $ui;
	}

	/**
	 *
	 * @param String $url
	 * @return Boolean
	 */
	protected function isPublicURL($url){
		$data = file_get_contents("http://isup.me/$url");
		return strpos($data, "is up.");
		//return @fsockopen($url, 80, $errno, $errstr, 30);
	}

}
