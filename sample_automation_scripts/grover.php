<?php // replace waitTillPresent with custom js waitFor function to prevent client read timeout issue
// and adust sleep time 
// updated download code use direct_download function and download by using invoice url to download the invoices 
// added config variable code to download invoices accorindg to without status=PAID filter if download_all_invoice == 1;

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

    // Server-Portal-ID: 1172646 - Last modified: 06.08.2025 15:47:23 UTC - User: 1

    /*Start script*/

    public $baseUrl = 'https://www.grover.com/de-en';
    public $loginUrl = 'https://www.grover.com/de-en/auth/login';
    public $invoicePageUrl = 'https://www.grover.com/business-en/your-payments?status=PAID';
    public $username_selector = 'input#email';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form button[data-testid="button-Red"]';
    public $check_login_failed_selector = 'p[data-testid="base-input-error"]';
    public $check_login_success_selector = 'a[href*="/dashboard"], a[href*="/your-payments"]';
    public $isNoInvoice = true;
    public $download_all_invoice = 0;

    /**

     * Entry Method thats called for a portal

     * @param Integer $count Number of times portal is retried.

     */
    private function initPortal($count)
    {
        $this->download_all_invoice =  isset($this->exts->config_array["download_all_invoice"]) ? (int)$this->exts->config_array["download_all_invoice"] : 0;
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->log('download_all_invoice ' . $this->download_all_invoice);

        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->baseUrl);

        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);

            $this->waitFor('div[data-testid="country_redirection_close_button"]');
            if ($this->exts->exists('div[data-testid="country_redirection_close_button"]')) {
                $this->exts->moveToElementAndClick('div[data-testid="country_redirection_close_button"]');
                sleep(3);
            }

            $this->fillForm(0);

            $this->checkFillTwoFactor();

            $gotoBusinessBtn = 'div[role="dialog"][data-state="open"] > div > div > button[data-testid="button-Primary"]';
            $this->waitFor($gotoBusinessBtn, 5);
            if ($this->exts->exists($gotoBusinessBtn)) {
                $this->exts->click_element($gotoBusinessBtn);
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            sleep(2);
            if ($this->download_all_invoice == 1) {
                // download all invoices
                $this->exts->log('Download all invoices');
                $this->exts->openUrl('https://www.grover.com/business-en/your-payments');
                $this->processInvoices();
            } else {
                $this->exts->openUrl($this->invoicePageUrl);
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {

            if ($this->exts->exists('div[id*="AUTH_FLOW"]')) {
                $this->exts->log("Account not ready !!!!");
                $this->exts->account_not_ready();
            }

            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector, 15);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

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
    public function checkLogin()
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

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'label[name="twoFactorAuthCode"] input';
        $two_factor_message_selector = 'form h5 > font, form div[dir="auto"] > font, form h5';
        $two_factor_submit_selector = '';

        $this->waitFor($two_factor_selector);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->querySelectorAll($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->querySelectorAll($two_factor_message_selector)[$i]->getText() . "\n";
                }
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }

            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(1);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                if ($this->exts->exists($two_factor_submit_selector)) {
                    $this->exts->moveToElementAndClick($two_factor_submit_selector);
                }

                sleep(10);

                if ($this->exts->querySelector($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function processInvoices()
    {

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $pagingCount = 0;
        $this->exts->log('Restrict Pages: ' . $restrictPages);
        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true;
        $this->exts->log('Restrict Date: ' . $restrictDate);

        $maxInvoices = 5;
        $invoiceCount = 0;

        $this->waitFor('div[data-testid="your-payments-payment-card"] div div button[data-testid="your-payments-download-invoice-button"]:not([disabled])', 5);
        $this->waitFor('div[data-testid="your-payments-payment-card"] div div button[data-testid="your-payments-download-invoice-button"]:not([disabled])', 5);
        $this->waitFor('div[data-testid="your-payments-payment-card"] div div button[data-testid="your-payments-download-invoice-button"]:not([disabled])', 5);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $maxAttempts = $restrictPages == 0 ? 50 : $restrictPages;

        $this->exts->update_process_lock();
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {

            $currentScroll = $this->exts->executeSafeScript('return window.scrollY;');
            $this->exts->execute_javascript('window.scrollBy(0, window.innerHeight);');
            $this->exts->log("Current Scroll: $currentScroll");
            sleep(5);

            $newScroll = $this->exts->executeSafeScript('return window.scrollY;');
            $this->exts->log("New Scroll: $newScroll");
            $attempt++;
            if ($currentScroll == $newScroll || $attempt == $maxAttempts) {
                $this->exts->log('No more space to scroll.');
                sleep(5);
                break;
            }
        }

        $rows = $this->exts->getElements('div[data-testid="your-payments-payment-card"]');
        $button = 'div div button[data-testid="button-Ghost"] , div div button[data-testid="your-payments-download-invoice-button"]:not([disabled])';
        $pagingCount++;

        foreach ($rows as $row) {
            $this->exts->log('Click  button by javascript');
            $this->exts->execute_javascript("arguments[0].click()", [$row]);

            sleep(2);
            $this->waitFor('a[href*="invoices"]');

            $invoiceLink = $this->exts->getElement('a[href*="invoices"]');

            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");

                $invoiceName =  '';
                if (preg_match('/invoices\/([^\/]+)\/pdf/', $invoiceUrl, $matches)) {
                    $invoiceId = $matches[1];
                    $invoiceName =  $invoiceId;
                }

                $invoiceAmount = $this->exts->extract('div:nth-child(4) > span:nth-child(1)', $row);
                $invoiceDate = $this->exts->extract('div:nth-child(2) > span', $row);
                $explodeDate = explode(' · Due ', $invoiceDate);
                $invoiceDate = !empty($explodeDate[1]) ? $explodeDate[1] : '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));

                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));

        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] =  $this->exts->parse_date(trim($invoice['invoiceDate']), 'M j, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $invoiceCount++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }


            $this->exts->log(' ');
            $this->exts->log('---------------------------INVOICE ITERATION END-------------------------');
            $this->exts->log(' ');
            $lastDate = !empty($invoice['invoiceDate']) && $invoice['invoiceDate'] <= $restrictDate;
            if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                break;
            } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                break;
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$portal = new PortalScriptCDP("optimized-chrome-v2", 'grover business', '2673335', 'Y2hyaXN0b3BoQGJsYWNrY2FiaW4uZGU=', 'NTAwNlRpZ2VyMjAyMCE=');
$portal->run();
