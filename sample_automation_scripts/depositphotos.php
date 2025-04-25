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

    // Server-Portal-ID: 721 - Last modified: 04.04.2025 13:18:08 UTC - User: 1

    public $baseUrl = 'https://de.depositphotos.com';
    public $loginUrl = 'https://de.depositphotos.com/login.html?backURL[page]=/home.html';
    public $invoicePageUrl = 'https://de.depositphotos.com/invoices.html';

    public $username_selector = 'input[name="username"], input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $remember_me_selector = '';
    public $submit_login_btn = 'button._submit, button[type="submit"]';

    public $checkLoginFailedSelector = 'form[novalidate] label[for="email"] > span:last-child,span.field-box__error';
    public $checkLoggedinSelector = 'img[src*="avatars"], a[href*="/logout"], a[href="/subscribe.html"], li[data-qa-group-name="logout"]';

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        // after load cookies and open base url, check if user logged in

        // Wait for selector that make sure user logged in
        sleep(10);
        if ($this->exts->exists('[data-qa="Avatar"]')) {
            $this->exts->moveToElementAndClick('[data-qa="Avatar"]');
            sleep(5);
        }
        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            $this->exts->clearCookies();

            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('div.login-user__social-btn_email')) {
                $this->exts->moveToElementAndClick('div.login-user__social-btn_email');
                sleep(5);
            } else {
                $tab_buttons = $this->exts->getElements('div[data-qa="LoginUser"] button');
                $this->exts->log('Finding Completted trips button...');
                foreach ($tab_buttons as $key => $tab_button) {
                    $tab_name = trim($tab_button->getAttribute('innerText'));
                    if (stripos($tab_name, 'Anmelden mit E-Mail') !== false) {
                        $this->exts->log('Completted trips button found');
                        $tab_button->click();
                        sleep(5);
                        break;
                    }
                }
            }
            $this->waitForLoginPage();
        }
    }

    private function waitForLoginPage($count = 1)
    {
        sleep(5);
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("1-filled-login");
            $this->checkFillRecaptcha();
            $this->exts->moveToElementAndClick($this->submit_login_btn);
            $this->waitForLogin();
        } else {
            if ($count < 5) {
                $count = $count + 1;
                $this->waitForLoginPage($count);
            } else {
                $this->exts->log('Timeout waitForLoginPage');
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }
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
                    $this->exts->executeSafeScript("arguments[0].innerHTML = '" . $this->exts->recaptcha_answer . "';", [$recaptcha_textareas[$i]]);
                }
                sleep(2);
                $this->exts->capture('recaptcha-filled');

                // Step 2, check if callback function need executed
                $gcallbackFunction = $this->exts->executeSafeScript('
                if(document.querySelector("[data-callback]") != null){
                    return document.querySelector("[data-callback]").getAttribute("data-callback");
                }

                var result = ""; var found = false;
                function recurse (cur, prop, deep) {
                    if(deep > 5 || found){ return;}console.log(prop);
                    try {
                        if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ return;}
                        if(prop.indexOf(".callback") > -1){result = prop; found = true; return;
                        } else { deep++;
                            for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
                        }
                    } catch(ex) { console.log("ERROR in function: " + ex); return; }
                }

                recurse(___grecaptcha_cfg.clients[0], "", 0);
                return found ? "___grecaptcha_cfg.clients[0]." + result : null;
            ');
                $this->exts->log('Callback function: ' . $gcallbackFunction);
                if ($gcallbackFunction != null) {
                    $this->exts->executeSafeScript($gcallbackFunction . '("' . $this->exts->recaptcha_answer . '");');
                    sleep(10);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
        }
    }

    private function waitForLogin($count = 1)
    {
        sleep(15);
        if ($this->exts->exists('section [data-qa="Avatar"]')) {
            $this->exts->moveToElementAndClick('section [data-qa="Avatar"]');
            sleep(5);
        }
        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            sleep(3);
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            $this->exts->success();
        } else {
            if ($count < 5) {
                $count = $count + 1;
                sleep(5);
                $this->waitForLogin($count);
            } else {
                $this->exts->log('Timeout waitForLogin');
                $this->exts->capture("LoginFailed");

                if ($this->exts->getElement($this->checkLoginFailedSelector) != null) {
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->loginFailure();
                }
            }
        }
    }

    private function processInvoices($count = 1)
    {
        sleep(5);
        if ($this->exts->getElement('div.buyer-menu-invoices table tbody tr a[href*="/invoices/"]') != null) {
            $this->exts->log('Invoices found');
            $this->exts->capture("4-page-opened");
            $invoices = [];

            $rows = $this->exts->getElements('div.buyer-menu-invoices table tbody tr');
            foreach ($rows as $row) {
                $invoiceUrl =  $this->exts->getElement('a[href*="/de/invoices/"]');
                if ($invoiceUrl != null) {
                    $invoiceLink = $invoiceUrl->getAttribute('href');

                    $invoiceName = $this->exts->extract('dtd:nth-child(3)', $row);
                    $invoiceDate = $this->exts->extract('td:nth-child(1)', $row);
                    $invoiceAmount = $this->exts->extract('td:nth-child(4)', $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceLink
                    ));
                }
            }

            // Download all invoices
            $this->exts->log('Invoices: ' . count($invoices));
            $count = 1;
            $totalFiles = count($invoices);
            foreach ($invoices as $invoice) {
                $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';

                $this->exts->log('date before parse: ' . $invoice['invoiceDate']);

                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
                $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
                $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
                $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
                $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoice['invoiceName'])) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->openUrl($invoice['invoiceUrl']);
                    sleep(5);
                    $this->exts->waitTillPresent('a.button-download-pdf');

                    $this->exts->log('Downloading invoice ' . $count . '/' . $totalFiles);

                    $downloaded_file = $this->exts->click_and_download('a.button-download-pdf', 'pdf', $invoiceFileName);
                    sleep(2);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceUrl,  $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                    $this->exts->log('Timeout when download ' . $invoiceFileName);
                }
            }
        } else {
            if ($count < 5) {
                $count = $count + 1;
                $this->processInvoices($count);
            } else {
                $this->exts->log('Timeout processInvoices');
                $this->exts->capture('4-no-invoices');
                $this->exts->no_invoice();
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
