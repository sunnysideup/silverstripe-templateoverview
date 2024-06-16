<?php

namespace Sunnysideup\TemplateOverview\Control;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use SebastianBergmann\Diff\Differ;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\TemplateOverview\Api\AllLinks;
use Sunnysideup\TemplateOverview\Api\ProvideTestUser;
use Sunnysideup\TemplateOverview\Api\W3cValidateApi;

class CheckAllTemplatesResponseController extends Controller
{
    /**
     * Defines methods that can be called directly.
     *
     * @var array
     */
    private static $allowed_actions = [
        'index' => 'ADMIN',
        'testone' => 'ADMIN',
    ];

    private static $url_segment = 'admin/templateoverviewsmoketestresponse';

    private static $use_w3_validation = false;

    private static $create_diff = false;

    private $guzzleCookieJar;

    private $guzzleClient;

    private $guzzleHasError = false;

    private $isSuccess = false;

    private $rawResponse = '';

    /**
     * Main function
     * has two streams:
     * 1. check on url specified in GET variable.
     * 2. create a list of urls to check.
     */
    public function testone(HTTPRequest $request)
    {
        $isCMSLink = (bool) $request->getVar('iscmslink');
        $testURL = $request->getVar('test') ?: null;
        // 1. actually test a URL and return the data
        if ($testURL) {
            $content = $this->testOneInner($testURL, $isCMSLink);
            echo $content;
            $this->doComparison($testURL, $isCMSLink);
            return;
        }

        user_error('no test url provided.');
    }

    public function testOneInner($testURL, $isCMSLink): string
    {
        $testUser = Injector::inst()->get(ProvideTestUser::class);
        $this->guzzleSetup();
        $testUser->getUser();
        $content = $this->testURL($testURL);
        $testUser->deleteUser();
        return $content;
    }

    /**
     * @return mixed
     */
    protected function guzzleSendRequest(string $url)
    {
        $this->guzzleHasError = false;
        $credentials = base64_encode(ProvideTestUser::get_user_email() . ':' . ProvideTestUser::get_password());

        try {
            $response = $this->guzzleClient->request(
                'GET',
                $url,
                [
                    'cookies' => $this->guzzleCookieJar,
                    'headers' => [
                        'PHP_AUTH_USER' => ProvideTestUser::get_user_email(),
                        'PHP_AUTH_PW' => ProvideTestUser::get_password(),
                    ],
                    'auth' => [
                        ProvideTestUser::get_user_email(),
                        ProvideTestUser::get_password(),
                    ],
                    'Authorization' => ['Basic ' . $credentials],
                ]
            );
        } catch (RequestException $requestException) {
            $this->rawResponse = $requestException->getResponse();
            $this->guzzleHasError = true;
            //echo Psr7\str($exception->getRequest());
            if ($requestException->hasResponse()) {
                $response = $requestException->getResponse();
                $this->rawResponse = $response->getStatusCode() . '|' . $response->getReasonPhrase();
            } else {
                $response = null;
            }
        }

        return $response;
    }

    protected function isJson($string)
    {
        $obj = json_decode($string);

        return JSON_ERROR_NONE === json_last_error() && 'object' === gettype($obj);
    }

    /**
     * ECHOES the result of testing the URL....
     *
     * @param string $url
     */
    protected function testURL($url)
    {
        if (strlen(trim($url)) < 1) {
            user_error('empty url'); //Checks for empty strings.
        }

        if (AllLinks::is_admin_link($url)) {
            $validate = false;
        } else {
            $validate = Config::inst()->get(self::class, 'use_w3_validation');
        }

        $testURL = Director::absoluteURL('/admin/templateoverviewloginandredirect/login')
            . '?BackURL=' . urlencode($url)
            . '&hash=' . ProvideTestUser::get_user_name_from_cache()
        ;
        $this->guzzleSetup();

        $start = microtime(true);
        $response = $this->guzzleSendRequest($testURL);
        $end = microtime(true);

        $data = [
            'status' => 'success',
            'httpResponse' => '?',
            'content' => '',
            'responseTime' => round($end - $start, 4),
            'type' => '',
            'length' => '',
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

        if (401 === $httpResponse) {
            $data['status'] = 'error';
            $data['content'] = 'Could not access: ' . $url;

            return json_encode($data);
        }

        //uncaught errors ...
        if ($this->rawResponse && 'Fatal error' === substr((string) $this->rawResponse, 0, 12)) {
            $data['status'] = 'error';
            $data['content'] = $this->rawResponse;
        } elseif (200 === $httpResponse && $this->rawResponse && strlen((string) $this->rawResponse) < 200) {
            if (! $this->isJson($this->rawResponse)) {
                $data['status'] = 'error - no response';
                $data['content'] = 'SHORT RESPONSE: ' . $this->rawResponse;
            }
        }

        $data['w3Content'] = 'n/a';

        if (200 !== $httpResponse) {
            $data['status'] = 'error';
            $data['content'] .= 'unexpected response: ' . $error . $this->rawResponse;
        } else {
            $this->isSuccess = true;
            $data['type'] = $response->getHeaders()['Content-Type'][0] ?? 'no-content-type';
            $data['length'] = $response->getHeaders()['Content-Length'][0] ?? '0';
            if ($validate) {
                $w3Obj = new W3cValidateApi();
                $data['w3Content'] = $w3Obj->W3Validate('', $this->rawResponse);
            }
        }

        if (Director::is_ajax()) {
            return json_encode($data);
        }

        $content = '';
        if (Director::isDev()) {
            $content .= '<p><strong>TEST URL:</strong> ' . $testURL . '</p>';
        }
        $content .= '<p><strong>URL:</strong> ' . $url . '</p>';
        $content .= '<p><strong>Status:</strong> ' . $data['status'] . '</p>';
        $content .= '<p><strong>HTTP response:</strong> ' . $data['httpResponse'] . '</p>';
        $content .= '<p><strong>Content:</strong> ' . htmlspecialchars($data['content']) . '</p>';
        $content .= '<p><strong>Response time:</strong> ' . $data['responseTime'] . '</p>';
        $content .= '<p><strong>Type:</strong> ' . $data['type'] . '</p>';

        return $content . ('<p><strong>W3 Content:</strong> ' . $data['w3Content'] . '</p>');
    }

    /**
     * creates the basic curl.
     */
    private function guzzleSetup()
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

    // private function debugme($lineNumber, $variable = "")
    // {
    //     if ($this->debug) {
    //         echo "<br />" . $lineNumber . ": " . round(memory_get_usage() / 1048576) . "MB" . "=====" . print_r($variable, 1);
    //         ob_flush();
    //         flush();
    //     }
    // }

    protected function doComparison(string $testURL, bool $isCMSLink)
    {
        if (! Director::is_ajax()) {
            $diff = 'Please install https://github.com/Kevin-Kip/meru/ to see diff.';
            $comparisonBaseURL = Config::inst()->get(self::class, 'comparision_base_url');
            $width = '98%';
            $style = 'border: none;';
            if ($comparisonBaseURL) {
                $width = '48%';
                $style = 'float: left;';
                if ($this->isSuccess && ! $isCMSLink && $this->Config()->create_diff) {
                    $otherURL = $comparisonBaseURL . $testURL;
                    $testContent = str_replace(rtrim(Director::absoluteBaseURL(), '/'), rtrim($comparisonBaseURL, '/'), $this->rawResponse);
                    $rawResponseOtherSite = @file_get_contents($otherURL);
                    if (class_exists(Differ::class)) {
                        $diff = (new Differ())->diff(
                            $testContent,
                            $rawResponseOtherSite
                        );
                        $rawResponseOtherSite = Convert::raw2htmlatt(str_replace("'", '\\\'', $rawResponseOtherSite));
                        $diff = '
                        <iframe id="iframe2" width="' . $width . '%" height="7000" srcdoc=\'' . $rawResponseOtherSite . '\' style="float: right;"></iframe>

                        <hr style="clear: both; margin-top: 20px; padding-top: 20px;" />
                        <h1>Diff</h1>
                        <link href="/resources/vendor/sunnysideup/templateoverview/client/css/checkalltemplates.css" rel="stylesheet" type="text/css" />
                        ' . $diff;
                    }
                }
            }

            $rawResponse = Convert::raw2htmlatt(str_replace("'", '\\\'', $this->rawResponse));
            echo '
                <h1>Response</h1>
            ';
            echo $diff;
            echo '
                <iframe id="iframe" width="' . $width . '" height="700" srcdoc=\'' . $rawResponse . '\' style="' . $style . '"></iframe>
            ';
        }
    }
}
