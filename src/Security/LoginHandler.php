<?php

namespace Sunnysideup\TemplateOverview\Security;

use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\RealMe\Extension\MemberExtension;
use SilverStripe\Security\Member;
use SilverStripe\Security\RequestAuthenticationHandler;
use SilverStripe\Security\Security;

class LoginHandler extends RequestHandler
{

    /**
     * @var array
     */
    private static $url_handlers = [
        '' => 'login',
    ];

    /**
     * @var array
     * @config
     */
    private static $allowed_actions = [
        'login'
    ];

    /**
     * @var string Called link on this handler
     */
    private $link;

    /**
     * @param string $link The URL to recreate this request handler
     */
    public function __construct($link)
    {
        $this->link = $link;
        parent::__construct();
    }

    /**
     * Return a link to this request handler.
     * The link returned is supplied in the constructor
     * @param null|string $action
     * @return string
     */
    public function link($action = null)
    {
        if ($action) {
            return Controller::join_links($this->link, $action);
        }

        return $this->link;
    }

    /**
     * URL handler for the log-in screen
     *
     * @return array
     */
    public function login()
    {
        return [
            'Form' => $this->loginForm(),
        ];
    }

    public function loginForm()
    {
        return $this->redirect($this->getBackURL() ?: '/');
    }

}
