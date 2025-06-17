<?php // addded code to triggerLoginFailedConfirmed

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

    // Server-Portal-ID: 1397 - Last modified: 02.05.2025 13:40:53 UTC - User: 1

    public $baseUrl = 'https://login-ciam.blau.de/signin/XUI/#login/';
    public $loginUrl = 'https://login-ciam.blau.de/signin/XUI/#login/';
    public $invoicePageUrl = 'https://www.blau.de/ecare/';

    public $username_selector = 'form[name="Login"] [name="IDToken1"]';
    public $password_selector = '#password [name="IDToken1"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[data-test-id="login-password-submit-button"][type="submit"]';

    public $check_login_failed_selector = '#login .alert-danger';
    public $check_login_success_selector = 'a[href*="/logout"]';

    public $isNoInvoice = true;

    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');
        sleep(2);
        $this->exts->loadCookiesFromFile();
        sleep(2);
        $this->exts->capture('1-init-page');

        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->openUrl($this->loginUrl);
            $this->exts->waitTillPresent('#usercentrics-root', 20);
            $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');
            sleep(2);
            $this->checkFillLogin();
            sleep(15);
            if ($this->exts->exists($this->username_selector)) {
                $this->clearChrome();
                $this->exts->openUrl($this->loginUrl);
                $this->checkFillLogin();
            } elseif (!$this->exts->exists($this->check_login_success_selector)) {
                $this->exts->openUrl($this->loginUrl);
                $this->checkFillLogin();
            }
            if ($this->exts->urlContains('auth/logout/')) {
                $this->exts->openUrl($this->loginUrl);
                sleep(10);
                $this->exts->execute_javascript('
                var shadow = document.querySelector("#usercentrics-root");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
                }
            ');
                sleep(2);
                $this->checkFillLogin();
                sleep(20);
            }
            $this->exts->moveToElementAndClick('.modal[role="dialog"] [role="document"] button.close');
            sleep(1);
            $this->exts->moveToElementAndClick('button[class="close"][data-testid="vt-hint-x-email-validation"]');
            sleep(1);
            $this->exts->capture("3-login-submitted");
        }
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->openUrl($this->loginUrl);
            $this->checkFillLogin();
        }
        if ($this->exts->getElement($this->check_login_success_selector) != null || $this->exts->getElement('//one-button[contains(text(),"Abmelden")]') != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');
            sleep(2);

            // Open invoices url and download invoice
            if ($this->exts->exists('div ul[data-description="main-menu"] li a[href*="contentId=rechnung"]')) {
                $this->exts->moveToElementAndClick('div ul[data-description="main-menu"] li a[href*="contentId=rechnung"]');
            } else if ($this->exts->exists('div ul[data-description="main-menu"] li a[href*="contentId=rechnung"]')) {
                $this->exts->moveToElementAndClick('div ul[data-description="main-menu"] li a[href*="contentId=rechnung"]');
            } else if ($this->exts->exists('div ul[data-description="main-menu"] li a[href*="contentId=rechnung"]')) {
                $this->exts->moveToElementAndClick('div ul[data-description="main-menu"] li a[href*="#rechnung/"]');
            } else {
                $subscriptionId = end(explode('subscriptionId=', $this->exts->getUrl()));
                $this->exts->openUrl('https://www.blau.de/ecareng/billing/uebersicht?subscriptionId=' . $subscriptionId);
            }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $isErrorMessage =  $this->exts->execute_javascript('document.body.innerHTML.includes("Nutzername und/oder Kennwort falsch")');

            $this->exts->log('isErrorMessage:: ' . $isErrorMessage);

            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('one-notification#errorMsg', null, 'title')), 'falsch') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('#login h1 + p', null, 'innerText')), 'blocked') !== false || strpos(strtolower($this->exts->extract('#login h1 + p', null, 'innerText')), 'gesperrt') !== false) {
                $this->exts->account_not_ready();
            } else if (strpos(strtolower($this->exts->extract('.alert.alert-info span.small', null, 'innerText')), 'Entschuldigung') !== false) {
                $this->exts->account_not_ready();
            } else if ($isErrorMessage) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 6; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->username_selector, 20);
        if ($this->exts->exists('#uc-banner-modal button#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('#uc-banner-modal button#uc-btn-accept-banner');
            sleep(5);
        }

        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
        if(shadow){
            shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
        }
    ');

        if ($this->exts->getElement($this->username_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->moveToElementAndClick('form[name="Login"] button[name="IDButton"][type="submit"]');
            sleep(10);

            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->remember_me_selector != '')
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(2);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page password not found');
                $this->exts->capture("2-login-page-password-not-found");
            }
        } else if ($this->exts->exists('one-input')) {
            // inputs in shadowRoot, cannot fill by JS
            // fill by JS cannot click button submit
            $this->exts->capture("2-login-page");
            $this->exts->click_by_xdotool('one-input', 130, 57);
            $this->exts->type_key_by_xdotool('Ctrl+a');
            $this->exts->type_key_by_xdotool('Delete');
            sleep(1);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(2);
            $this->exts->click_by_xdotool('one-input', 130, 127);
            sleep(1);
            $this->exts->type_text_by_xdotool($this->password);
            $this->exts->capture("3-login-page-filled-shadow-root");

            sleep(8);
            // click button submit
            $this->exts->execute_javascript('
            var shadow = document.querySelector("one-container.isnotNovumApp one-cluster one-button").shadowRoot;
            if(shadow) shadow.querySelector("button:not([disabled])").click();
        ');
            $this->exts->capture("3-login-page-submit-shadow-root");
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('[data-testid="vt-billing.show-invoices"]', 30);

        if ($this->exts->exists('#uc-banner-modal button#uc-btn-accept-banner')) {
            $this->exts->moveToElementAndClick('#uc-banner-modal button#uc-btn-accept-banner');
            sleep(5);
        }
        if ($this->exts->exists('button[data-dismiss="modal"][data-tracking-description="cms___close"]')) {
            $this->exts->moveToElementAndClick('button[data-dismiss="modal"][data-tracking-description="cms___close"]');
            sleep(5);
        }
        $load_more = 0;
        $this->exts->log('Trying to load more');

        while ($load_more < 20 && $this->exts->exists('[data-testid="vt-billing.show-invoices"]')) {
            $load_more++;
            $this->exts->moveToElementAndClick('[data-testid="vt-billing.show-invoices"]');
            sleep(5);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = count($this->exts->querySelectorAll('invoice-panel div.panel-body'));

        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->querySelectorAll('invoice-panel div.panel-body')[$i];
            if ($this->exts->getElement('a[data-description="bill-download-link"].btn.medallia', $row) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a[data-description="bill-download-link"].btn.medallia', $row);
                $invoiceName = explode(" ", end(explode("vom ", $this->exts->extract('div.text', $row, 'innerText'))))[0];
                $invoiceName = str_replace('.', '-', $invoiceName);
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = trim(explode(" ", end(explode("vom ", $this->exts->extract('div.text', $row, 'innerText'))))[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.pricing', $row, 'innerText'))) . ' EUR';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $downloaded_file = $this->exts->click_and_download($download_button, 'pdf', $invoiceFileName);
                    sleep(10);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
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
