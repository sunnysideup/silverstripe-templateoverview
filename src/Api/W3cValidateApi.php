<?php

namespace Sunnysideup\TemplateOverview\Api;

/*
   Author:    Jamie Telin (jamie.telin@gmail.com), currently at employed Zebramedia.se

   Scriptname: W3C Validation Api v1.0 (W3C Markup Validation Service)

*/

class W3cValidateApi
{
    private $baseURL = 'http://validator.w3.org/check';

    private $output = 'soap12';

    private $uri = '';

    private $fragment = '';

    private $postVars = [];

    private $validResult = false;

    private $errorCount = 0;

    private $showErrors = true;

    private $errorList = [];

    public function W3Validate($uri = '', $fragment = '')
    {
        if ($uri) {
            $this->setUri($uri);
        } elseif ($fragment) {
            $this->setFragment($fragment);
        }
        $this->validate();
        if ($this->validResult) {
            $type = 'PASS';
            $color1 = '#00CC00';
        } else {
            $type = 'FAIL';
            $color1 = '#FF3300';
        }
        $errorDescription = '';
        if ($this->errorCount) {
            $errorDescription = ' - ' . $this->errorCount . 'errors: ';
            if ($this->showErrors) {
                if (count($this->errorList) > 0) {
                    $errorDescription .= '<ul style="display: none;"><li>' . implode('</li><li>', $this->errorList) . '</li></ul>';
                }
            } else {
                $errorDescription .= '<a href="' . $this->baseURL . '?uri=' . urlencode($uri) . '">check</a>';
            }
        }

        return '<div style="background:' . $color1 . ';"><a href="#" class="showMoreClick">' . $type . '</a></strong>' . $errorDescription . '</div>';
    }

    private function makePostVars()
    {
        $this->postVars['output'] = $this->output;
        if ($this->fragment) {
            $this->postVars['fragment'] = $this->fragment;
        } elseif ($this->uri) {
            $this->postVars['uri'] = $this->uri;
        }
    }

    private function setUri($uri)
    {
        $this->uri = $uri;
    }

    private function setFragment($fragment)
    {
        $fragment = preg_replace('#\s+#', ' ', $fragment);
        $this->fragment = $fragment;
    }

    private function validate()
    {
        sleep(1);

        $this->makePostVars();

        $user_agent = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)';
        $options = [
            CURLOPT_CUSTOMREQUEST => 'POST',        //set request type post or get
            CURLOPT_POST => 1,            //set to GET
            CURLOPT_USERAGENT => $user_agent, //"test from www.sunnysideup.co.nz",//$user_agent, //set user agent
            CURLOPT_COOKIEFILE => 'cookie.txt', //set cookie file
            CURLOPT_COOKIEJAR => 'cookie.txt', //set cookie jar
            CURLOPT_RETURNTRANSFER => true,     // return web page
            CURLOPT_HEADER => false,    // don't return headers
            CURLOPT_FOLLOWLOCATION => true,     // follow redirects
            CURLOPT_ENCODING => '',       // handle all encodings
            CURLOPT_AUTOREFERER => true,     // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
            CURLOPT_TIMEOUT => 120,      // timeout on response
            CURLOPT_MAXREDIRS => 10,       // stop after 10 redirects
            CURLOPT_POSTFIELDS => $this->postVars,
            CURLOPT_URL => $this->baseURL,
        ];
        $httpCode = '000';

        // Initialize the curl session
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        // Execute the session and capture the response
        $out = curl_exec($ch);

        //$err               = curl_errno( $ch );
        //$errmsg            = curl_error( $ch );
        //$header            = curl_getinfo( $ch );
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (200 === $httpCode) {
            $doc = simplexml_load_string($out);
            $doc->registerXPathNamespace('m', 'http://www.w3.org/2005/10/markup-validator');

            //valid ??
            $nodes = $doc->xpath('//m:markupvalidationresponse/m:validity');
            $this->validResult = 'true' === strval($nodes[0]);

            //error count ??
            $nodes = $doc->xpath('//m:markupvalidationresponse/m:errors/m:errorcount');
            $this->errorCount = strval($nodes[0]);
            //errors
            $nodes = $doc->xpath('//m:markupvalidationresponse/m:errors/m:errorlist/m:error');
            foreach ($nodes as $node) {
                //line
                $nodes = $node->xpath('m:line');
                $line = strval($nodes[0]);
                //col
                $nodes = $node->xpath('m:col');
                $col = strval($nodes[0]);
                //message
                $nodes = $node->xpath('m:message');
                $message = strval($nodes[0]);
                $this->errorList[] = $message . "({$line},{$col})";
            }
        }

        return $httpCode;
    }
}
