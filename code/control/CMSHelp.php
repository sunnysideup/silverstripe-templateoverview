<?php

class CMSHelp extends Page_Controller implements PermissionProvider
{

    /**
     *@var String name of the directory in which the help files are kept
     *
     */
    private static $help_file_directory_name = "_help";

    /**
     *@var String name of the directory in which the help files are kept
     *
     */
    private static $dev_file_directory_name = "_dev";


    /**
     *@var String urlsegment for the controller
     *
     */
    private static $url_segment = "admin/help";

    private static $permission_code = "CMS_HELP_FILES_PERMISSION_CODE";

    /**
     *@var String urlsegment for the controller
     *
     */
    private static $allowed_actions = array(
        'download' => 'CMS_HELP_FILES_PERMISSION_CODE'
    );

    /**
     * standard SS Method
     *
     */
    public function init()
    {
        // Only administrators can run this method
        if (!Permission::check("CMS_HELP_FILES_PERMISSION_CODE")) {
            Security::permissionFailure($this, _t('Security.PERMFAILURE', ' This page is secured and you need rights to access it. Please contact the site administrator is you believe you should be able to access this page.'));
        }
        parent::init();
        Requirements::themedCSS("typography", "typography");
        Requirements::javascript(THIRDPARTY_DIR."/jquery/jquery.js");
    }


    /**
     * standard SS Method
     *
     */
    public function index()
    {
        return $this->renderWith('Page');
    }

    public function getContent()
    {
        return $this->renderWith('CMSHelp');
    }


    /**
     * returns the Link to the controller
     *
     * @return String -
     */
    public function Link($action = "")
    {
        $str = "/".$this->config()->get("url_segment")."/";
        if ($action) {
            $str .= $action . '/';
        }
        return $str;
    }

    public function download($request)
    {
        $fileName = urldecode($request->getVar('file'));
        $files = self::get_list_of_files($this->Config()->get("help_file_directory_name"));
        foreach ($files as $file) {
            if ($fileName === $file['FileName']) {
                $fileName = $file['FullLocation'];
                if (file_exists($fileName)) {
                    return SS_HTTPRequest::send_file(file_get_contents($fileName), $file['FileName']);
                }
            }
        }
        die('ERROR');
    }

    /**
     * @return ArrayList of help files
     *
     *
     */
    public function HelpFiles()
    {
        $dos = new ArrayList();
        $fileArray = self::get_list_of_files($this->Config()->get("help_file_directory_name"));
        if ($fileArray && count($fileArray)) {
            $linkArray = array();
            foreach ($fileArray as $file) {
                $dos->push(new ArrayData($file));
            }
        }
        return $dos;
    }

    /**
     * @return String - title for project
     *
     *
     */
    public function SiteTitle()
    {
        $sc = SiteConfig::current_site_config();
        if ($sc && $sc->Title) {
            return $sc->Title;
        }
        return Director::absoluteURL();
    }


    /**
     * @param String $location - folder location without start and end slahs (e.g. assets/myfolder )
     * @return Array - array of help files
     *
     *
     */
    public static function get_list_of_files($location)
    {
        $fileArray = array();
        $directory = "/".$location."/";
        $baseDirectory = Director::baseFolder().$directory;
        //get all image files with a .jpg extension.
        $images = self::get_list_of_files_in_directory($baseDirectory, array("png", "jpg", "gif", 'pdf'));
        $me = Injector::inst()->get('CMSHelp');
        //print each file name
        if (is_array($images) && count($images)) {
            foreach ($images as $key => $image) {
                if ($image) {
                    if (file_exists($baseDirectory.$image)) {
                        $fileArray[$key]["FileName"] = $image;
                        $fileArray[$key]["FullLocation"] = $baseDirectory.$image;
                        $fileArray[$key]["Link"] = $me->Link('download').'?file='.urldecode($image);
                        $fileArray[$key]["Title"] = self::add_space_before_capital($image);
                    }
                }
            }
        }
        return $fileArray;
    }


    /**
     * @param String $directory - location of the directory
     * @param Array $extensionArray - array of extensions to include (e.g. Array("png", "mov");)
     *
     * @return Array - list of all files in a directory
     */
    public static function get_list_of_files_in_directory($directory, $extensionArray)
    {
        //	create an array to hold directory list
        $results = array();
        // create a handler for the directory
        $handler = @opendir($directory);
        if (!is_dir($directory)) {
            return false;
        }
        if ($handler) {
            //open directory and walk through the filenames
            while ($file = readdir($handler)) {
                // if file isn't this directory or its parent, add it to the results
                if ($file != "." && $file != ".." && !is_dir($file)) {
                    //echo $file;
                    $extension = substr(strrchr($file, '.'), 1);
                    if (in_array($extension, $extensionArray)) {
                        $results[] = $file;
                    }
                }
            }
            // tidy up: close the handler
            closedir($handler);
            // done!
            asort($results);
        }
        return $results;
    }




    /**
     * returns the Link to the controller
     * @param String $string - input
     * @return String
     */
    private static function add_space_before_capital($string)
    {
        $string = preg_replace('/(?<!\ )[A-Z\-]/', ' $0', $string);
        $extension = substr(strrchr($string, '.'), 0);
        $string = str_replace(array('-', $extension, '.'), "", $string);
        return $string;
    }

    public function providePermissions()
    {
        $perms[Config::inst()->get("CMSHelp", "permission_code")] = array(
            'name' => "Download Help Files",
            'category' => "Help",
            'sort' => 0
        );
        return $perms;
    }
}
