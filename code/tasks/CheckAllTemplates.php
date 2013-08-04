<?php

/**
 * @description (see $this->description)
 *
 * @authors: Andrew Pett [at] sunny side up .co.nz, Nicolaas [at] Sunny Side Up .co.nz
 * @package: templateoveriew
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
			echo $this->testURL($testOne);
			$this->cleanup();
		}

		//2. create a list of
		else {
			Requirements::javascript(THIRDPARTY_DIR . '//ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.min.js');
			$this->classNames = $this->ListOfAllClasses();
			$this->modelAdmins = $this->ListOfAllModelAdmins();
			$this->allNonAdmins = $this->prepareClasses();
			$this->allAdmins = $this->array_push_array($this->modelAdmins, $this->prepareClasses(1));
			$sections = array("allNonAdmins", "allAdmins");
			$count = 0;
			echo "<h1><a href=\"#\" class=\"start\">start</a> | <a href=\"#\" class=\"stop\">stop</a></h1>
			<table border='1'>
			<tr><th>Link</th><th>HTTP response</th><th>response TIME</th><th class'error'>error</th></tr>";
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
							jQuery('#'+ID).css('background-image', 'url(/templateoverview/images/loading.gif)');
							jQuery.ajax({
								url: checker.baseURL,
								type: 'get',
								data: {'test': testLink, 'admin': isAdmin},
								success: function(data, textStatus){
									checker.item = null;
									jQuery('#'+ID).html(data).css('background-image', 'none');
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
	private function testURL($url) {
		if(strlen(trim($url)) < 1) {
			user_error("empty url"); //Checks for empty strings.
		}

		$url = Director::absoluteURL($url);

		curl_setopt($this->ch, CURLOPT_URL, $url);
		$response = curl_exec($this->ch);
		$httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		if($httpCode == "401") {
			$this->createAndLoginUser();
			return $this->testURL($url);
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
		echo $html;
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
	private function ListOfAllClasses(){
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
						foreach($modelsToAdd as $model => $extraInfo) {
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
