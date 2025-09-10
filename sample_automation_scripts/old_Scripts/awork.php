<?php // replaced waitTillPresent to waitFor function and adjust sleep time

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

    // Server-Portal-ID: 753114 - Last modified: 08.08.2025 14:14:34 UTC - User: 1

    public $baseUrl = 'https://app.awork.com/login';
    public $loginUrl = 'https://app.awork.com/login';
    public $invoicePageUrl = '';

    public $username_selector = '[data-test="login-mail-input"] input';
    public $password_selector = 'input#password-input';
    public $remember_me_selector = '';
    public $submit_login_selector = '[data-test="login-submit-button"] button';

    public $check_login_failed_selector = 'article.info-box';
    public $check_login_success_selector = 'aw-current-user-context-menu img.avt__image, aw-current-user-context-menu figure.avt-initials';

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
            if ($this->exts->exists('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll')) {
                $this->exts->click_by_xdotool('button#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll');
            }
            if ($this->exts->exists('[data-test="login-with-mail-button"] button')) {
                $this->exts->click_by_xdotool('[data-test="login-with-mail-button"] button');
            }
            $this->fillForm(0);
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(2);

            $this->waitFor("//a[contains(@class, 'menu-link') and (.//span[contains(text(), 'Show more')] or .//span[contains(text(), 'Mehr anzeigen')])]", 20);
            $this->exts->click_element("//a[contains(@class, 'menu-link') and (.//span[contains(text(), 'Show more')] or .//span[contains(text(), 'Mehr anzeigen')])]");
            sleep(1);
            $this->waitFor('[data-intercom-target="menu-item-settings"] a', 10);
            $this->exts->click_element('[data-intercom-target="menu-item-settings"] a');
            $this->waitFor('[data-intercom-target="tabbar-item-settings-subscription"] button', 20);

            if ($this->exts->exists('aw-open-desktop-links-toast')) {
                $this->exts->execute_javascript('
                var toast = document.querySelector("aw-open-desktop-links-toast");
                if(toast){
                    toast.style.display = "none";
                }
            ');
                sleep(1);
            }

            $this->exts->click_element('[data-intercom-target="tabbar-item-settings-subscription"] button');
            $this->waitFor("//button[contains(@class, 'btn') and (contains(., 'invoice') or contains(., 'Rechnung'))]", 20);
            $this->exts->click_element("//button[contains(@class, 'btn') and (contains(., 'invoice') or contains(., 'Rechnung'))]");
           
            for ($i = 0; $i < 10 && $this->exts->getElement('iframe[src*="manage-subscription"]') == null; $i++) {
                sleep(2);
            }
            sleep(5);

            if ($this->exts->exists('iframe[src*="manage-subscription"]')) {
                $iframe_subscription = $this->exts->makeFrameExecutable('iframe[src*="manage-subscription"]');
                sleep(2);
                for ($i = 0; $i < 10 && $iframe_subscription->getElement('div[data-cb-id="portal_billing_history"]') == null; $i++) {
                    sleep(2);
                }
                $iframe_subscription->moveToElementAndClick('div[data-cb-id="portal_billing_history"]');
            }

            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'correct') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }


    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 5);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->exts->exists($this->remember_me_selector)) {
                    $this->exts->click_by_xdotool($this->remember_me_selector);
                    sleep(1);
                }

                $this->exts->click_by_xdotool($this->submit_login_selector);
                sleep(2); // Portal itself has one second delay after showing toast
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }

    /**

     * Method to Check where user is logged in or not

     * return boolean true/false

     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            sleep(5);
            $this->waitFor($this->check_login_success_selector, 20);
            if ($this->exts->exists($this->check_login_success_selector)) {

                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");

                $isLoggedIn = true;
            }
        } catch (Exception $exception) {

            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    private function processInvoices($paging_count = 1)
    {
        if ($this->exts->exists('iframe[src*="manage-subscription"]')) {
            $iframe_subscription = $this->exts->makeFrameExecutable('iframe[src*="manage-subscription"]');
            sleep(12);
            $iframe_subscription->click_element('div.cb-history__more');
            sleep(12);
            $this->exts->capture("4-invoices-page");
            $invoices = [];

            $rows = $iframe_subscription->querySelectorAll('div.cb-history__list');
            foreach ($rows as $row) {
                $downloadBtn = $iframe_subscription->getElement('div.cb-invoice__link', $row);
                if ($downloadBtn != null) {
                    $invoiceUrl = '';
                    $invoiceName = $iframe_subscription->extract('div.cb-invoice__title', $row) . '-' . $iframe_subscription->extract('span.cb-invoice__text', $row);
                    $invoiceAmount =   $iframe_subscription->extract('span.cb-invoice__price', $row);
                    $invoiceDate =  $iframe_subscription->extract('span.cb-invoice__text', $row);

                    // $downloadBtn = $this->exts->querySelector('div.cb-invoice__link', $row);

                    array_push($invoices, array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl,
                        'downloadBtn' => $downloadBtn
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
                $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd. M. Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

                // $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                // $downloaded_file = $this->exts->click_and_download($invoice['downloadBtn'], 'pdf', $invoiceFileName);
                $iframe_subscription->execute_javascript("arguments[0].click();", [$invoice['downloadBtn']]);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
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
