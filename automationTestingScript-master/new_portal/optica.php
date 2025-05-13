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
    public $baseUrl = 'https://www.optica.de/app-meinoptica#portalApi';
    public $loginUrl = 'https://www.optica.de/meinoptica-login';
    public $invoicePageUrl = 'https://www.optica.de/app-meinoptica#portalApi/rezepte';

    public $username_selector = 'input[id="username"]';
    public $password_selector = 'input[id="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button[id="kc-login"]';

    public $check_login_failed_selector = 'div.alert-error span';
    public $check_login_success_selector = 'a[class*="topmenu__signout"]';

    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->waitTillPresent('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');

        if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
            $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            sleep(7);
        }

        $this->exts->loadCookiesFromFile();

        sleep(10);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
            // Accecpt cookies
            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->moveToElementAndClick('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
                sleep(7);
            }
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            if ($this->exts->exists('div[data-id="rezepte"]')) {
                $this->exts->moveToElementAndClick('div[data-id="rezepte"]');
                sleep(10);
            }

            if ($this->exts->exists('button[aria-label="Close"]')) {
                $this->exts->moveToElementAndClick('button[aria-label="Close"]');
                sleep(5);
            }
            $this->exts->waitTillPresent('iframe[src*="kuop.optica.de"]');

            $this->switchToFrame('iframe[src*="kuop.optica.de"]');
            sleep(5);

            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->type_key_by_xdotool('Return');

                $this->exts->waitTillPresent('iframe[src*="kuop.optica.de"]');

                $this->switchToFrame('iframe[src*="kuop.optica.de"]');
                sleep(5);
            }
            // try second time
            if ($this->exts->exists($this->password_selector)) {
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->type_key_by_xdotool('Return');

                $this->exts->waitTillPresent('iframe[src*="kuop.optica.de"]');

                $this->switchToFrame('iframe[src*="kuop.optica.de"]');
                sleep(5);
            }

            // user has no permission to access invoices
            if ($this->exts->exists($this->password_selector)) {
                $this->exts->no_permission();
            } else {

                if ($this->exts->exists('div[class="kuop-datepicker-monatlich__input"] input.kuop-datepicker-monatlich__input__field:nth-child(1)')) {
                    $this->exts->moveToElementAndClick('div[class="kuop-datepicker-monatlich__input"] input.kuop-datepicker-monatlich__input__field:nth-child(1)');
                    sleep(5);
                }

                if ($this->exts->exists('button[class="month-button ng-star-inserted"]:nth-child(1)')) {
                    $this->exts->moveToElementAndClick('button[class="month-button ng-star-inserted"]:nth-child(1)');
                    sleep(4);
                }

                if ($this->exts->exists('div[class*="elements-wrappe"] button.kuop-search-button')) {
                    $this->exts->moveToElementAndClick('div[class*="elements-wrappe"] button.kuop-search-button');
                    sleep(10);
                }
                $this->downloadInvoices();
            }

            $this->exts->success();
        } else {
            if (stripos($this->exts->extract($this->check_login_failed_selector), 'Ungültiger Benutzername oder Passwort.') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if (stripos($this->exts->extract($this->check_login_failed_selector), 'Die Aktion ist nicht mehr gÃ¼ltig. Bitte fahren Sie nun mit der Anmeldung fort.') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);

        $this->exts->waitTillPresent('iframe[src*="kuop.optica.de"]');

        $this->switchToFrame('iframe[src*="kuop.optica.de"]');

        $this->exts->waitTillPresent($this->username_selector);
        try {
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

                $this->exts->capture("1-login-page-filled");

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                    sleep(10);
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
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
            for ($wait = 0; $wait < 15 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
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

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    private function downloadInvoices()
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('table[role="presentation"] tbody tr[class="k-master-row k-table-row ng-star-inserted"]');
        $this->exts->capture("4-invoices-classic");

        $rows = $this->exts->getElements('table[role="presentation"] tbody tr[class="k-master-row k-table-row ng-star-inserted"]');
        foreach ($rows as $key => $row) {
            $downloadBtn = $this->exts->getElement('td[data-kendo-grid-column-index="0"] span.kuop-table-numbers', $row);
            if ($downloadBtn != null) {
                try {
                    $downloadBtn->click();
                    sleep(15);
                } catch (\Exception $e) {
                    $this->exts->log('Error:: ' . $e->getMessage());
                }

                $invoiceName = $this->exts->extract('div.kuop-rezeptdetail__patienten-info  div[class*="patienten-info__versicherten-nummer"] div.kuop-info-block__info');
                $invoiceDate = $this->exts->extract('div.kuop-rezeptdetail__patienten-info  div[class*="patienten-info__geburts-datum"] div.kuop-info-block__info');
                $invoiceAmount = '';

                $this->exts->executeSafeScript("let tabs = document.querySelectorAll('ul[role=\"tablist\"] li[id*=\"k-tabstrip-tab\"][aria-disabled=\"false\"][tabindex=\"-1\"]'); 
                  if (tabs.length > 1) { 
                  tabs[1].click(); 
                }");

                sleep(15);
                $captureName = "invoices-detail-" . $key . "";
                $this->exts->capture($captureName);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $invoiceAmount = '';
                $invoiceFileName = $invoiceName . '.pdf';
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $downloaded_file = $this->exts->click_and_download('button[dir="ltr"] span[class*="fa fa-file-pdf-o"]', 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
                $this->isNoInvoice = false;

                if ($this->exts->exists('button[dir="ltr"] span[class*="fa fa-angle-left"]')) {
                    $this->exts->moveToElementAndClick('button[dir="ltr"] span[class*="fa fa-angle-left"]');
                    sleep(10);
                }
            }
        }
    }
}
