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

	protected $description = "Will go through main URLs on the website, checking for HTTP errors (e.g. 404)";

	/**
	  * List of URLs to be checked. Excludes front end pages (Cart pages etc).
	  */
	private $modelAdmins = array();

	private $allAdmins = array();

	/**
	  * Pages to check by class name. For example, for "ClassPage", will check the first instance of the cart page.
	  */
	private $classNames = array(
		"CartPage",
		"CheckoutPage",
		"OrderConfirmationPage",
		"AccountPage",
		"Product",
		"ProductGroup",
		"ProductGroupSearchPage"
	);


	public function run($request) {
		set_time_limit(0);
		$this->classNames = $this->ListOfAllClasses();
		$this->modelAdmins = $this->ListOfAllModelAdmins();
		$classURLs = $this->prepareClasses();
		$username = "TEMPLATEOVERVIEW_URLCHECKER___";
		$password = rand(1000000000,9999999999);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

		//Test Class pages
		echo "<h4>Testing class pages before logging in</h4><br /><ul>";
		$errors = $this->testURLs($classURLs, $ch); // Test the class pages i.e. CartPage before logging in.
		echo "</ul>";
		echo "<strong><span" . ( $errors > 0 ? " style='color:red'" : " style='color:green'").">".$errors." errors.</span></strong><br /><br />";

		//Make temporary admin member
		echo "<strong>Making admin member (".$username.") on the fly...</strong><br />";
		$adminMember = Member::get()->filter(array("Email" => $username))->first();
		if($adminMember != NULL) {
			echo "<strong>Member alread exists... deleting</strong><br />";
			$adminMember->delete();
		}
		$Member = new Member();
		$Member->Email = $username;
		$Member->Password = $password;
		$Member->write();
		$Member->Groups()->add(Group::get()->filter(array("code" => "administrators"))->first());

		echo "Made admin<br />";;
		echo "Logging in..<br />";

		$username = $Member->Email;
		$loginUrl = Director::absoluteURL('/Security/LoginForm');
		$ch = $this->login($ch, $loginUrl, $username, $password); // Will return 'false' if we failed to log in.
		if(!$ch) {
			echo "<span style='color:red'>There was an error logging in!</span><br />";

		} else {
			echo "<span style='color:green'>Successfully made contact with login form.</span><br />";
			echo "<h4>Retrying class pages after login.</h4><ul>";
			//$errors = $this->testURLs($classURLs, $ch);
			echo "</ul>";
			echo "<strong><span" . ( $errors > 0 ? " style='color:red'" : " style='color:green'").">".$errors." errors.</span></strong><br /><br />";


			// Will add /admin/edit/show/$ID for each of the {@link #classNames} to {@link #urls}
			$this->allAdmins = $this->array_push_array($this->modelAdmins, $this->prepareClasses(1));

			echo "<h4>Testing admin URLs</h4><ul>";
			$errors = $this->testURLs($this->allAdmins, $ch);
			echo "</ul>";
			echo "<strong><span" . ( $errors > 0 ? " style='color:red'" : " style='color:green'").">".$errors." errors.</span></strong><br /><br />";


			curl_close($ch);

		}
		$Member->delete();
	}

	public function getDescription() {
		return $this->description;
	}

	public function getTitle() {
		return $this->title;
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
	  * @param Curl Handle $ch A curl handle to use (will be returned later if successful).
	  * @param String $loginUrl URL of the form to post to
	  * @param String $username Username
	  * @param String $password Password
	  * @return Curl Handle|Boolean Returns the curl handle if successfully contacted log in form, else 'false'
	  */
	private function login($ch, $loginUrl, $username, $password) {
		curl_setopt($ch, CURLOPT_URL, $loginUrl);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'Email='.$username.'&Password='.$password);
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');


		//execute the request (the login)
		$loginContent = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($httpCode == 200) return $ch;
		return false;
	}

	/**
	  * Tests the URLs for a 200 HTTP code.
	  * @param Array(String) $urls an array of urls (relative to base site e.g. /admin) to test
	  * @param Curl Handle Curl handle to use
	  * @return Int number of errors
	  */
	private function testURLs($urls, $ch) {
		$errors = 0;
		foreach($urls as $url) {
			if(strlen(trim($url)) < 1) continue; //Checks for empty strings.

			$url = Director::absoluteURL($url);

			curl_setopt($ch, CURLOPT_URL, $url);
			$response = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
			$possibleError = false;
			if((strlen($response) < 500) || (substr($response, 0, 11) == "Fatal error")) {
				$possibleError = true;
			}
				//Content-Length
			if($httpCode == 200 ) {
				echo "<li style='color:green'>";
			}
			else {
				echo "<li style='color:red'>";
				$errors++;
			}
			if($possibleError) {
				echo " <span style='color: red;'>CHECK FOR ERRORS</span> ";
			}
			echo "[<a href=".$url.">".$url."</a>]: HTTPCODE [".$httpCode."]</li>";
		}
		return $errors;
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

	function ListOfAllClasses(){
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

	function ListOfAllModelAdmins(){
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
