<?php // waitFor switchToFrame adjust sleep updated email button selecotr

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

    // Server-Portal-ID: 240035 - Last modified: 30.06.2025 14:59:46 UTC - User: 1

    public $baseUrl = 'https://chayns.site/id/money';
    public $loginUrl = 'https://chayns.site/id/money';
    public $invoicePageUrl = 'https://chayns.site/id/money';

    public $username_selector = 'input[name="email-phone"]';
    public $password_selector = 'input[type="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div[class*="EMailPhone"] > button.beta-chayns-button.ellipsis';

    public $check_login_failed_selector = 'div#dialog-root div.header__description';
    public $check_login_success_selector = 'div[class="logout-button-wrapper"]';

    public $isNoInvoice = true;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {

        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);

            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }


            $this->exts->success();
        } else {

            $iframe = $this->exts->makeFrameExecutable('iframe#OverlayFrame');

            if ($iframe && $iframe->exists('div.form div > div > button[class="button"]')) {
                $this->exts->log("Wrong credential !!!");
                $this->exts->loginFailure(1);
            }

            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);

        $this->waitFor('iframe#TappIFrame_', 30);

        $this->switchToFrame('iframe#TappIFrame_');


        sleep(5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                if ($this->exts->exists('div[class*="EMailPhone"] > button.beta-chayns-button.ellipsis')) {
                    $this->exts->moveToElementAndClick('div[class*="EMailPhone"] > button.beta-chayns-button.ellipsis');
                    sleep(7);
                }

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->log("Remember Me");
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->capture("1-login-page-filled");
                sleep(5);

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
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

    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->waitFor($this->check_login_success_selector, 10);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    public $totalInvoices = 0;
    private function processInvoices($paging_count = 1)
    {
        sleep(10);

        $this->waitFor('iframe.cw-iframe', 30);
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $iframe = $this->exts->makeFrameExecutable('iframe.cw-iframe');

        if (!$iframe) {
            $this->exts->capture("---INVOICES PAGE IFRAME NOT FOUND!!!---");

            return false;
        }

        $accordionSelector = 'div.money-view > div.react-accordion:nth-child(7) div.accordion__head';

        $iframe->waitTillPresent($accordionSelector, 30);
        if ($iframe->exists($accordionSelector)) {
            $iframe->click_element($accordionSelector);
        }

        $yearAccordionSelector = 'div.money-view > div.react-accordion:nth-child(7) div.accordion__body div.accordion--wrapped:not(.interval)';
        $iframe->waitTillPresent($yearAccordionSelector, 30);

        $years = $iframe->querySelectorAll($yearAccordionSelector);

        foreach ($years as $year) {
            $yearButton = $iframe->querySelector('div.accordion__head', $year);
            $iframe->execute_javascript("arguments[0].click();", [$yearButton]);

            sleep(2);
        }

        $iframe->waitTillPresent('div.documents div.list-item--clickable', 30);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $iframe->querySelectorAll('div.documents div.list-item--clickable');

        foreach ($rows as $row) {

            if ($this->totalInvoices >= 50 && $restrictPages != 0) {
                return;
            }

            if ($iframe->querySelector('div.list-item__title.ellipsis', $row) != null) {

                $invoiceUrl = '';
                $invoiceName = $iframe->extract('div.list-item__title.ellipsis', $row, 'innerText');
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $iframe->extract('div.list-item__right', $row, 'innerText'))) . ' EUR';

                $invoiceDate = $iframe->extract('div.list-item__subtitle.ellipsis', $row, 'innerText');
                $explodeDate = explode(',', $invoiceDate);
                $invoiceDate = !empty($explodeDate[0]) ? $explodeDate[0] : '';

                $downloadBtn = $iframe->querySelector('div.list-item__title.ellipsis', $row);

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                    'downloadBtn' => $downloadBtn
                ));

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceUrl: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date(trim($invoiceDate), 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $iframe->execute_javascript("arguments[0].click();", [$downloadBtn]);

                sleep(2);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                $invoiceFileName = basename($downloaded_file);

                $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                $this->exts->log('invoiceName: ' . $invoiceName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                    sleep(1);
                    $this->totalInvoices++;
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
