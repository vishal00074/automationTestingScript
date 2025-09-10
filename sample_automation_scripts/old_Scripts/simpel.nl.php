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

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            try {
                // Start portal script execution
                $this->initPortal(0);
            } catch (\Exception $exception) {
                $this->exts->log('Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }


            $this->exts->log('Execution completed');

            $this->exts->process_completed();
            $this->exts->dump_session_files();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }
    // Server-Portal-ID: 9556 - Last modified: 13.06.2025 13:59:43 UTC - User: 1

    public $baseUrl = "https://mijn.simpel.nl/";
    public $username_selector = '#login [name=username]';
    public $password_selector = '#login [name=password]';
    public $submit_btn = '#login [type=submit]';
    public $logout_btn = '[href*="/uitloggen"], button[data-qa="account-menu-logout"]';
    public $isNoInvoice = true;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $isCookieLoaded = false;
        if ($this->exts->loadCookiesFromFile()) {
            sleep(1);
            $isCookieLoaded = true;
        }

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($isCookieLoaded) {
            $this->exts->capture("Home-page-with-cookie");
        } else {
            $this->exts->capture("Home-page-without-cookie");
        }

        if (!$this->checkLogin()) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
        }

        if ($this->exts->exists('button[onclick="acceptAll()"]')) {
            $this->exts->moveToElementAndClick('button[onclick="acceptAll()"]');
            sleep(4);
        }

        $this->fillForm();
        sleep(20);


        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->exts->moveToElementAndClick('[href*="/facturen"]');
            sleep(20);

            $this->downloadInvoice(0);

            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->capture("LoginFailed");
            if (stripos($this->exts->extract('.--error', null, 'innerText'), "wachtwoord klopt niet") !== false) { //wachtwoord klopt niet
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */
    function fillForm($count = 1)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            if ($this->exts->exists($this->username_selector)) {

                $this->checkFillRecaptcha();
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                if ($this->exts->exists('div.vc-checkbox-input input[name="remember"]')) {
                    $this->exts->moveToElementAndClick('div.vc-checkbox-input input[name="remember"]');
                    sleep(2);
                }

                $this->exts->capture("1-pre-login-1");


                $this->exts->moveToElementAndClick($this->submit_btn);
                sleep(15);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }


    private function checkFillRecaptcha()
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
                for ($i = 0; $i < count($recaptcha_textareas); $i++) {
                    $this->exts->execute_javascript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->execute_javascript('
            if(document.querySelector("[data-callback]") != null){
                document.querySelector("[data-callback]").getAttribute("data-callback");
            } else {
                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                found ? "___grecaptcha_cfg.clients[0]." + result : null;
            }
        ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->execute_javascript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->exts->exists($this->logout_btn)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    function downloadInvoice($count)
    {
        $this->exts->log("Begin download invoice - " . $count);
        try {
            if ($this->exts->exists('.invoice-item')) {
                $this->exts->capture("2-download-invoice");

                $invoices = array();

                $receipts = $this->exts->getElements('.invoice-item');

                foreach ($receipts as $receipt) {

                    try {
                        $receiptDate = trim($this->exts->extract('.col-invoice-date', $receipt));
                    } catch (\Exception $exception) {
                        $receiptDate = null;
                    }

                    if ($receiptDate != null && $this->exts->extract('.col-invoice-download-invoice a', $receipt, 'href') != null) {
                        $this->exts->log($receiptDate);

                        $receiptName = $this->exts->extract('.col-invoice-number', $receipt);


                        $this->exts->log($receiptName);
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd F Y', 'Y-m-d');
                        $this->exts->log($parsed_date);
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $this->exts->log($receiptFileName);

                        $receiptAmount = trim($this->exts->extract('.col-invoice-price', $receipt));
                        $receiptAmount = preg_replace('/[^\d\.,]/m', '', $receiptAmount);
                        $receiptAmount = $receiptAmount . ' EUR';
                        $this->isNoInvoice = false;

                        $receiptUrl = $this->exts->extract('.col-invoice-download-invoice a', $receipt, 'href');
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'receiptUrl' => $receiptUrl,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                foreach ($invoices as $invoice) {
                    try {

                        $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                        sleep(1);
                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                            sleep(2);
                        }
                        sleep(3);
                    } catch (\Exception $exception) {
                        $this->exts->log("Exception downloading invoice - " . $exception->getMessage());
                    }
                };
            }

            $this->exts->capture("4-invoices-page");
            $invoices = [];

            $rows = $this->exts->getElements('div[class*="invoices-table-desktop__row"]');
            foreach ($rows as $row) {
                $tags = $this->exts->getElements('div[class*="invoices-table-desktop__cell"]', $row);
                if (count($tags) >= 4 && $this->exts->getElement('a[href*="facturen/"]:not([href*="specificatie"])', $row) != null) {
                    $invoiceUrl = $this->exts->getElement('a[href*="facturen/"]:not([href*="specificatie"])', $row)->getAttribute("href");
                    $invoiceName = trim(explode('/', end(explode('facturen/', $invoiceUrl)))[0]);
                    $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                    $invoiceDate = trim(end(explode($invoiceName, $invoiceDate)));
                    $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div[class*="price"]', $row, 'innerText'))) . ' EUR';

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    ));
                    $this->isNoInvoice = false;
                }
            }

            // Download all invoices
            $this->exts->log('Invoices found: ' . count($invoices));
            foreach ($invoices as $invoice) {
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd F Y', 'Y-m-d', 'nl');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
