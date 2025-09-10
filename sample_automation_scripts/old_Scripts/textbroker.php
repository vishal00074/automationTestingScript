<?php // uncommented loadCookiesFromFile and clearCookies handle empty invoices case  updated invoiceName selector

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 414 - Last modified: 27.08.2025 13:29:45 UTC - User: 1

    // Start Script

    public $baseUrl = 'https://intern.textbroker.de/client/home';
    public $username_selector = 'input[name="email"]';
    public $password_selector = 'input[name="password"]';
    public $submit_login_selector = 'form[action="/login/login"] button[type="submit"]';
    public $check_login_success_selector = 'li.logout, a[href*="/logout"]';
    public $isNoInvoice = true;
    public $totalFiles = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        // Load cookies
       
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');
        if ($this->exts->exists('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]')) {
            $this->exts->moveToElementAndClick('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]');
            sleep(5);
        }
        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLoggedIn()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->exts->moveToElementAndClick('[aria-describedby="cookieconsent:desc"] a.cc-allow');
            sleep(2);
            $this->exts->moveToElementAndClick('button.cm__btn[data-role="all"]');
            sleep(5);
            $this->checkFillLogin();
            sleep(5);
            if (strpos(strtolower($this->exts->extract('.alert.fadeInDown')), 'recaptcha') !== false) {
                // $this->exts->moveToElementAndClick('.alert.fadeInDown [data-notify="dismiss"]');
                $this->checkFillRecaptcha();
                sleep(5);
                $this->exts->moveToElementAndClick('.alert.fadeInDown [data-notify="dismiss"]');
                sleep(30);
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(10);
            }

            if (strpos(strtolower($this->exts->extract('.alert.fadeInDown')), 'recaptcha') !== false) {
                // $this->clearChrome();
                $this->exts->openUrl($this->baseUrl);
                sleep(15);
                $this->exts->moveToElementAndClick('button.cm__btn[data-role="all"]');
                $this->checkFillLogin();
            }

            $this->checkFillTwoFactor();

            sleep(10);
            if ($this->exts->exists('tb-side-nav-menu.ng-star-inserted div.mat-menu-trigger, button.c-toolbar__menu-button')) {
                $this->exts->moveToElementAndClick('tb-side-nav-menu.ng-star-inserted div.mat-menu-trigger,button.c-toolbar__menu-button');
                sleep(5);
            }
            if ($this->exts->exists('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]')) {
                $this->exts->moveToElementAndClick('div.c-side-nav__menu [title="Mein Konto"] [aria-haspopup="menu"]');
                sleep(5);
            }

            sleep(8);
            if ($this->exts->exists('div[class="cm__btn-group"] button[data-role="all"]')) {
                $this->exts->moveToElementAndClick('div[class="cm__btn-group"] button[data-role="all"]');
                sleep(5);
            }
        }

        // then check user logged in or not
        if ($this->checkLoggedIn()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");
            $this->exts->moveToElementAndClick('button.cm__btn[data-role="all"]');
            sleep(5);
            // Open invoices url and download invoice
            $this->exts->openUrl('https://intern.textbroker.de/client/account/invoices');
            // if($this->exts->exists('iframe[src*="/transactions"]')){
            //  $this->exts->switchToFrame('iframe[src*="/transactions"]');
            //  sleep(1);
            // }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->totalFiles == 0) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (strpos(strtolower($this->exts->extract('.alert.fadeInDown')), 'passwor') !== false || strpos(strtolower($this->exts->extract('.alert.fadeInDown')), strtolower('Die Benutzerdaten sind nicht korrekt'))) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->urlContains('/loginFailBlock')) {
                $this->exts->loginFailure(1);
            } else {
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
    private function checkFillTwoFactor()
    {
        $two_factor_selector = '.c-two-factor-code-verification [formcontrolname="code"] input';
        $two_factor_message_selector = 'tb-two-factor-authenticate-view h1 + div > div:first-child';
        $two_factor_submit_selector = 'button[type="submit"]';

        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                $this->exts->moveToElementAndClick('[formcontrolname="trustedDevice"]');
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }
    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");
            $this->exts->moveToElementAndClick('label[for="userTypeClient"]');

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(10);
            $this->checkFillRecaptcha();
            $this->exts->capture("2-login-page-filled");
            sleep(5);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(5);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    private function checkLoggedIn()
    {
        $isLoggedIn = false;
        $this->exts->waitTillPresent($this->check_login_success_selector, 20);
        if ($this->exts->exists($this->check_login_success_selector)) {
            $isLoggedIn = true;
        }
        return $isLoggedIn;
    }
    private function processInvoices($paging_count = 1)
    {
        $this->exts->waitTillPresent('iframe[src*="/transactions"]', 20);
        if ($this->exts->exists('iframe[src*="/transactions"]')) {
            $invoice_frame = $this->exts->makeFrameExecutable('iframe[src*="/transactions"]');
            $this->exts->capture("4-invoices-page-1");
            $rows = count($invoice_frame->querySelectorAll('table#dataTable_invoices-table > tbody > tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $invoice_frame->getElements('table#dataTable_invoices-table > tbody > tr')[$i];
                $invoiceName = trim($invoice_frame->extract('td:nth-child(2)', $row));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceDate = trim(explode(', ', $invoice_frame->extract('td:nth-child(3)', $row))[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoice_frame->extract('td:nth-child(4)', $row))) . ' EUR';
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('Date parsed: ' . $parsed_date);
                $this->isNoInvoice = false;
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $row->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$row]);
                    }
                    sleep(2);
                    $invoice_frame->moveToElementAndClick('form[action*="/downloadInvoicePDF"] button[type="submit"]');
                    sleep(5);
                    $invoice_frame->moveToElementAndClick('button#open-button');
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $pdf_content = file_get_contents($downloaded_file);
                        if (stripos($pdf_content, "%PDF") !== false) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                            $this->totalFiles++;
                        } else {
                            $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                    if ($this->exts->exists('button#open-button')) {
                        $this->exts->execute_javascript('history.back();');
                        sleep(10);
                    }
                }
                sleep(3);
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if (
                $restrictPages == 0 &&
                $paging_count < 50 &&
                $this->exts->getElement('#invoices-pagination a.next-item, #dataTable_invoices-table_next:not(.disabled) a') != null
            ) {
                $paging_count++;
                $invoice_frame->moveToElementAndClick('#invoices-pagination a.next-item, #dataTable_invoices-table_next:not(.disabled) a');
                sleep(5);
                $this->processInvoices($paging_count);
            }
        } else {
            $this->exts->capture("4-invoices-page");
            $rows = count($this->exts->querySelectorAll('table#dataTable_invoices-table > tbody > tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->querySelectorAll('table#dataTable_invoices-table > tbody > tr')[$i];
                $invoiceName = trim($this->exts->extract('td:nth-child(1)', $row));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceDate = trim(explode(', ', $this->exts->extract('td:nth-child(2)', $row))[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('td:nth-child(4)', $row))) . ' EUR';
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('Date parsed: ' . $parsed_date);
                $this->isNoInvoice = false;
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $row->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$row]);
                    }
                    sleep(4);
                    $this->exts->moveToElementAndClick('form[action*="/downloadInvoicePDF"] button[type="submit"]');
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $pdf_content = file_get_contents($downloaded_file);
                        if (stripos($pdf_content, "%PDF") !== false) {
                            $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                            sleep(1);
                            $this->totalFiles++;
                        } else {
                            $this->exts->log(__FUNCTION__ . ":: Not Valid PDF - " . $downloaded_file);
                        }
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                    if ($this->exts->exists('button#open-button')) {
                        $this->exts->execute_javascript('history.back();');
                        sleep(10);
                    }
                }
                sleep(3);
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if (
                $restrictPages == 0 &&
                $paging_count < 50 &&
                $this->exts->getElement('#invoices-pagination a.next-item, #dataTable_invoices-table_next:not(.disabled) a') != null
            ) {
                $paging_count++;
                $this->exts->moveToElementAndClick('#invoices-pagination a.next-item, #dataTable_invoices-table_next:not(.disabled) a');
                sleep(5);
                $this->processInvoices($paging_count);
            }
        }
    }
}


$portal = new PortalScriptCDP("optimized-chrome-v2", 'TextBroker', '2673351', 'bWF0aGlhcy5rb3N1YkBrZW5zaW5ndG9uLWludGVybmF0aW9uYWwuY29t', 'S29zdTYsLi0=');
$portal->run();
