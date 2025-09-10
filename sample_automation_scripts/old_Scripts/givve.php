<?php // replace exists to isExists and waitTillPresent to waitFor and handle empty invoices name added code to restrict invoices

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


    // Server-Portal-ID: 39701 - Last modified: 21.07.2025 14:25:57 UTC - User: 1

    public $baseUrl = "https://b2b.givve.com";
    public $username_selector = '#mat-input-0';
    public $password_selector = '#mat-input-1';
    public $submit_btn = '#content form [type=submit], form button.mat-primary';
    public $logout_btn = 'mat-sidenav-content app-toolbar-profile, button[data-cy="logout-button"]';
    public $isNoInvoice = true;

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
            $this->exts->capture("after-login-clicked");
            $this->fillForm(0);
            sleep(20);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            //accept cookies
            if ($this->isExists('button.low-button')) {
                $this->exts->click_element('button.low-button');
            }
            $this->exts->moveToElementAndClick('mat-dialog-container button[data-cy="opt-out-clear-cache-dialog-button"]');
            sleep(2);
            $this->exts->openUrl('https://b2b.givve.com/invoices');
            if ($this->isExists('button.low-button')) {
                $this->exts->click_element('button.low-button');
            }
            $this->downloadInvoice();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {

            $this->exts->capture("LoginFailed");
            if (strpos(strtolower($this->exts->extract('form[data-cy="login-form"] div.error-row', null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {

            for ($i = 0; $i < 15 && $this->exts->getElement($this->username_selector) == null; $i++) {
                sleep(2);
            }
            if ($this->isExists($this->username_selector)) {
                sleep(2);
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->capture("1-pre-login-1");
                $this->checkFillRecaptcha();

                $this->exts->click_element($this->submit_btn);
                sleep(10);
            } else if ($this->isExists("iframe[src*=\"https://www.google.com/recaptcha/api2/anchor?\"]") && $this->isExists("textarea[name=\"g-recaptcha-response\"]")) {
                $this->checkFillRecaptcha();
                $this->fillForm($count + 1);
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
        if ($this->isExists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                // Step 1 fill answer to textarea
                $this->exts->log(__FUNCTION__ . "::filling reCaptcha response..");
                $recaptcha_textareas =  $this->exts->querySelectorAll($recaptcha_textarea_selector);
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

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            if ($this->isExists($this->logout_btn) && $this->isExists($this->username_selector) == false) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            } else if ($this->isExists('button[data-cy="toolbar-profile-button"]')) {
                $this->exts->moveToElementAndClick('button[data-cy="toolbar-profile-button"]');
                sleep(1);
                if ($this->isExists($this->logout_btn)) {
                    $isLoggedIn = true;
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    public $totalInvoices = 0;

    private function downloadInvoice()
    {
        for ($i = 0; $i < 15 && $this->exts->getElement('.data-list-item app-invoice-list-item') == null; $i++) {
            sleep(2);
        }
        $this->exts->capture("4-invoices-page");
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($this->isExists('.mat-card-list .mat-card-content app-load-more button') && $restrictPages == 0) {
            $this->exts->log('Trying to load more');
            $this->exts->moveToElementAndClick('.mat-card-list .mat-card-content app-load-more button');
            sleep(5);
        }
        $loadCount = 1;
        while ($this->isExists('button[data-cy="givve-load-more-button"]')) {
            if ($this->isExists('button[data-cy="givve-load-more-button"]')) {
                $this->exts->log('Clicking on button trying to load more :' . $loadCount);
                $this->exts->execute_javascript(
                    'document.querySelector("button[data-cy=\'givve-load-more-button\']").click();'
                );
                $loadCount++;
                sleep(8);
            }
        }
        $currentPageHeight = 0;
        for ($i = 0; $i < 15 && $currentPageHeight != $this->exts->execute_javascript('return document.body.scrollHeight;'); $i++) {
            $this->exts->log('Scroll to bottom ' . $currentPageHeight);
            $currentPageHeight = $this->exts->execute_javascript('return document.body.scrollHeight;');
            $this->exts->execute_javascript('window.scrollTo(0,document.body.scrollHeight);');
            sleep(7);
        }
        $invoices = [];
        $rows = count($this->exts->querySelectorAll('.data-list-item app-invoice-list-item'));
        for ($i = 0; $i < $rows; $i++) {


            if ($restrictPages != 0 && $this->totalInvoices >= 50) {
                return;
            }

            $row = $this->exts->querySelectorAll('.data-list-item app-invoice-list-item')[$i];
            if ($this->exts->querySelector('.list-gross-amount', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->querySelector('.list-gross-amount', $row);
                $invoiceDate = $this->exts->querySelector('div:nth-child(2)', $row)->getAttribute("innerText");
                $invoiceName = $this->exts->querySelector('div:nth-child(1)', $row)->getAttribute("innerText");
                $invoiceAmount = $this->exts->querySelector('.list-gross-amount span', $row)->getAttribute("innerText");
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' EUR';
                $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);
                $this->isNoInvoice = false;
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }
                if ($download_button != null) {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                }

                sleep(3);
                //click Actions invoice, show download button
                if (!$this->isExists('app-toolbar-download-btn button')) {
                    $this->exts->moveToElementAndClick('app-invoice-detail-toolbar-menu button[data-cy="actions-menu-trigger"]');
                    sleep(3);
                }
                if ($this->isExists('app-toolbar-download-btn button')) {
                    $this->exts->log("Choose download button");
                    $this->exts->moveToElementAndClick('app-toolbar-download-btn button');
                    sleep(15);
                }
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->exts->executeSafeScript('history.back();');
                sleep(11);
            } else if ($this->exts->querySelector('div[data-cy="download-invoice-btn"]', $row) != null) {
                $download_button = $this->exts->querySelector('div[data-cy="download-invoice-btn"]', $row);
                $invoiceDate = trim($this->exts->extract('div:nth-child(2)', $row));
                $invoiceName = trim($this->exts->extract('div:nth-child(1)', $row));
                $invoiceAmount = trim($this->exts->extract('div:nth-child(4)', $row));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount)) . ' EUR';
                $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);
                $this->isNoInvoice = false;
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                    continue;
                }
                if ($download_button != null) {
                    try {
                        $this->exts->log('Click download popup button');
                        $this->exts->click_element($download_button);
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(2);
                }

                if ($this->isExists('button[data-cy="arrow-up-button"]')) {
                    $this->exts->execute_javascript(
                        'var btn = document.querySelector("button[data-cy=\'arrow-up-button\']"); 
	                     if (btn) { btn.style.display = "none"; }'
                    );
                    sleep(5);
                }
                $this->waitFor('button.download-btn-invoice');
                if ($this->isExists('button[data-cy="go-back-button"]')) {
                    $this->exts->click_element('button[data-cy="go-back-button"]');
                    sleep(10);
                }
                //click Actions invoice, show download button
                sleep(5);
                $downloaded_file = $this->exts->click_and_download('button.download-btn-invoice', 'pdf', $invoiceFileName);
                $this->exts->wait_and_check_download('pdf');

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
