<?php

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package uwa
 *
 * @copyright   GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/remote-chrome/utils/');

$gmi_browser_core = realpath('/var/www/remote-chrome/utils/GmiChromeManager.php');
require_once($gmi_browser_core);
class PortalScriptCDP
{

    private $exts;
    public $setupSuccess = false;
    private $chrome_manage;
    private $username;
    private $password;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/remote-chrome/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $username, $password);
        $this->setupSuccess = true;
    }

    /*Define constants used in script*/
    public $baseUrl = 'https://outgrow.co/';
    public $loginUrl = 'https://app.outgrow.co/login';
    public $invoicePageUrl = '';

    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[type="submit"]';

    public $check_login_failed_selector = 'div.alert-danger';
    public $check_login_success_selector = 'div.custom-name div.user_logout';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        $this->exts->loadCookiesFromFile();

        $this->exts->waitTillPresent('div.nn_nav_btns li[id="dashboard-login"]', 10);

        if ($this->exts->exists('div.nn_nav_btns li[id="dashboard-login"]')) {
            $this->exts->moveToElementAndClick('div.nn_nav_btns li[id="dashboard-login"]');
            sleep(5);
        }

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $elements = $this->exts->getElements('div[class="name-list company_details"] a');
            try {
                // Click on first element
                $elements[0]->click();
            } catch (\Exception $e) {
                $this->exts->log(__FUNCTION__ . '::Invoice button Error ' . $e->getMessage());
            }

            $this->exts->waitTillPresent('ul a[href="#invoice"][class="hoverEffect"]');

            if ($this->exts->exists('ul a[href="#invoice"][class="hoverEffect"]')) {
                $this->exts->moveToElementAndClick('ul a[href="#invoice"][class="hoverEffect"]');
            }

            $this->downloadInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));
            if (stripos($error_text, "something isn't quite right. please enter a correct email and password.") !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);

        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(5);
            }

            $this->checkFillHcaptcha();

            $this->exts->capture("1-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(5);
            }
        }
    }

    private function checkFillHcaptcha()
    {
        $this->exts->waitTillPresent('div[class="g-recaptcha"] iframe[src*="hcaptcha.com/captcha/v1/"]');
        $hcaptcha_iframe_selector = 'div[class="g-recaptcha"] iframe[src*="hcaptcha.com/captcha/v1/"]';
        if ($this->exts->exists($hcaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($hcaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&sitekey=", $iframeUrl)))[0];
            $this->exts->log('SiteKey: ' . $data_siteKey);
            $jsonRes = $this->exts->processHumanCaptcha($data_siteKey, $this->exts->getUrl());
            $captchaScript = '
            function submitToken(token) {
            document.querySelector("[name=g-recaptcha-response]").innerText = token;
            document.querySelector("[name=h-captcha-response]").innerText = token;
            }
            submitToken(arguments[0]);
            ';
            $params = array($jsonRes);

            sleep(2);
            $guiId = $this->exts->extract('input[id*="captcha-data"]', null, 'value');
            $guiId = trim(explode('"', end(explode('"guid":"', $guiId)))[0]);
            $this->exts->log('guiId: ' . $guiId);
            $this->exts->execute_javascript($captchaScript, $params);
            $str_command = 'var btn = document.createElement("INPUT");
            var att = document.createAttribute("type");
            att.value = "hidden";
            btn.setAttributeNode(att);
            var att = document.createAttribute("name");
            att.value = "captchaTokenInput";
            btn.setAttributeNode(att);
            var att = document.createAttribute("value");
            btn.setAttributeNode(att);
            form1 = document.querySelector("#captcha_form");
            form1.appendChild(btn);';
            $this->exts->execute_javascript($str_command);
            sleep(2);
            $captchaScript = '
            function submitToken1(token) {
            document.querySelector("[name=captchaTokenInput]").value = token;
            }
            submitToken1(arguments[0]);
            ';
            $captchaTokenInputValue = '%7B%22guid%22%3A%22' . $guiId . '%22%2C%22provider%22%3A%22' . 'hcaptcha' . '%22%2C%22appName%22%3A%22' . 'orch' . '%22%2C%22token%22%3A%22' . $jsonRes . '%22%7D';
            $params = array($captchaTokenInputValue);
            $this->exts->execute_javascript($captchaScript, $params);

            $this->exts->log($this->exts->extract('input[name="captchaTokenInput"]', null, 'value'));
            sleep(2);
            $gcallbackFunction = 'captchaCallback';
            $this->exts->execute_javascript($gcallbackFunction . '("' . $jsonRes . '");');

            $this->exts->switchToDefault();
            sleep(10);
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->exists($this->check_login_success_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function downloadInvoices($count = 0)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table tbody tr');
        $this->exts->capture("4-invoices-classic");

        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key => $row) {
            $invoiceBtn = $this->exts->getElement('a', $row);
            if ($invoiceBtn != null) {
                sleep(2);
                $invoiceUrl = '';
                $invoiceName = $invoiceBtn->getAttribute("id");
                $invoiceName =  $invoiceName ?? time();
                $invoiceDate = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(3)', $row);;

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' .  $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' .  $invoiceUrl);
                $invoiceFileName =  $invoiceName . '.pdf';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' .  $invoiceDate);

                $downloaded_file = $this->exts->click_and_download($invoiceBtn, 'pdf', $invoiceFileName);
                sleep(2);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceUrl,  $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}
