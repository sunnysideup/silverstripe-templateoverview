<?php

/**
 * @description (see $this->description)
 *
 * @authors: Andrew Pett [at] sunny side up .co.nz, Nicolaas [at] Sunny Side Up .co.nz
 * @package: templateoveriew
 * @sub-package: tasks
 **/

class CheckAllTemplates extends BuildTask {


	public function getDescription() {
		return $this->description;
	}

	public function getTitle() {
		return $this->title;
	}


	protected $title = 'Check URLs for HTTP errors';

	protected $description = "Will go through main URLs on the website, checking for HTTP errors (e.g. 404)";

	/**
	  * List of URLs to be checked. Excludes front end pages (Cart pages etc).
	  */
	private $modelAdmins = array();

	/**
	 *
	 * all of the public acessible links
	 */
	private $allOpenLinks = array();

	/**
	 *
	 * all of the admin acessible links
	 */
	private $allAdmins = array();

	/**
	  * Pages to check by class name. For example, for "ClassPage", will check the first instance of the cart page.
	  */
	private $classNames = array();

	private $ch = null;

	private $member = null;

	private $username = "";

	private $password = "";


	public function run($request) {
		set_time_limit(0);
		$asAdmin = empty($_REQUEST["admin"]) ? false : true;
		$testOne = isset($_REQUEST["test"]) ? $_GET["test"] : null;
		//actually test a URL and return the data
		if($testOne) {
			$this->setupCurl();
			if($asAdmin) {
				$this->createAndLoginUser();
			}
			echo $this->testURL($testOne);
		}
		//create a list of
		else {
			Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
			$this->classNames = $this->ListOfAllClasses();
			$this->modelAdmins = $this->ListOfAllModelAdmins();
			$this->allNonAdmins = $this->prepareClasses();
			$this->allAdmins = $this->array_push_array($this->modelAdmins, $this->prepareClasses(1));
			$sections = array("allNonAdmins", "allAdmins");
			$count = 0;
			echo "<h1><a href=\"#\" class=\"start\">start</a> | <a href=\"#\" class=\"stop\">stop</a></h1>
			<table border='1'>
			<tr><th>Link</th><th>HTTP response</th><th>response TIME</th><th class'error'>error</th></tr>";
			foreach($sections as $key => $section) {
				foreach($this->$section as $link) {
					$count++;
					$id = "ID".$count;
					$linkArray[] = array("IsAdmin" => $key, "Link" => $link, "ID" => $id);
					echo "
						<tr id=\"$id\" class=".($key ? "isAdmin" : "notAdmin")."><td>$link</td><td></td><td></td><td></td></tr>
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
								.css('border', '1px solid blue')
								.css('background-repeat', 'no-repeat')
								.css('background-image', '/templateoverview/images/loading.gif');
							jQuery.ajax({
								url: checker.baseURL,
								type: 'get',
								data: {'test': testLink, 'admin': isAdmin},
								success: function(data, textStatus){
									checker.item = null;
									jQuery('#'+ID).html(data);
									//jQuery('h1').fadeOut(2000);
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
									//jQuery('h1').fadeOut(2000);
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
		$this->member->Groups()->add(Group::get()->filter(array("code" => "administrators"))->first());

		$loginUrl = Director::absoluteURL('/Security/LoginForm');
		$this->ch = $this->login($loginUrl); // Will return 'false' if we failed to log in.
		if(!$this->ch) {
			echo "<span style='color:red'>There was an error logging in!</span><br />";
		}
	}

	private function setupCurl(){
		$this->ch = curl_init();
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, TRUE);
	}

	private function cleanup(){
		if($this->member) {
			$this->member->delete();
		}
		curl_close($this->ch);
	}


	/**
	  * Takes {@link #$classNames}, gets the URL of the first instance of it (will exclude extensions of the class) and
	  * appends to the {@link #$urls} list to be checked
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
	  * Will try log in to SS with given username and password.
	  * @param Curl Handle $this->ch A curl handle to use (will be returned later if successful).
	  * @param String $loginUrl URL of the form to post to
	  * @param String $username Username
	  * @param String $password Password
	  * @return Curl Handle|Boolean Returns the curl handle if successfully contacted log in form, else 'false'
	  */
	private function login($loginUrl) {
		curl_setopt($this->ch, CURLOPT_URL, $loginUrl);
		curl_setopt($this->ch, CURLOPT_POST, 1);
		curl_setopt($this->ch, CURLOPT_POSTFIELDS, 'Email='.$this->username.'&Password='.$this->password);
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, 'cookie.txt');


		//execute the request (the login)
		$loginContent = curl_exec($this->ch);
		$httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		if($httpCode == 200) return $this->ch;
		return false;
	}

	/**
	  * Tests the URLs for a 200 HTTP code.
	  * @param Array(String) $urls an array of urls (relative to base site e.g. /admin) to test
	  * @param Curl Handle Curl handle to use
	  * @return Int number of errors
	  */
	private function testURL($url) {
		if(strlen(trim($url)) < 1) {
			user_error("empty url"); //Checks for empty strings.
		}

		$url = Director::absoluteURL($url);

		curl_setopt($this->ch, CURLOPT_URL, $url);
		$response = curl_exec($this->ch);
		$httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		$timeTaken = curl_getinfo($this->ch, CURLINFO_TOTAL_TIME);
		$timeTaken = number_format((float)$timeTaken, 2, '.', '');
		$length = curl_getinfo($this->ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
		$possibleError = false;
		if((strlen($response) < 500) || ($length < 500) || (substr($response, 0, 11) == "Fatal error")) {
			$error = "<span style='color: red;'>short response</span> ";
		}
		$error = "none";
		$html = "";
		if($httpCode == 200 ) {
			$html .= "<td style='color:green'><a href='$url' style='color: grey!important; text-decoration: none;'>$url</a></td>";
		}
		else {
			$html .= "<td style='color:red'><a href='$url' style='color: red!important; text-decoration: none;'>$url</a></td>";
		}
		if($possibleError) {
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

	private function ListOfAllClasses(){
		$pages = array();
		$templateOverviewPage = TemplateOverviewPage::get()->First();
		if(!$templateOverviewPage) {
			$templateOverviewPage = singleton("TemplateOverviewPage");
		}
		$list = $templateOverviewPage->ListOfAllClasses();
		foreach($list as $page) {
			$pages[] = $page->ClassName;
		}
		return $pages;
	}

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
}
