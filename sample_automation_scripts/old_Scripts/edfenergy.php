<?php //  updated login and download code update checkfillform function and invoicepage function

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

    // Server-Portal-ID: 8880 - Last modified: 31.01.2025 14:18:07 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://edfenergy.com/myaccount/login";
    public $loginUrl = "https://edfenergy.com/myaccount/login";
    public $homePageUrl = "https://edfenergy.com/myaccount/login";
    public $login_button_selector = ".signin-interceptor";
    public $billingPageUrl = "https://www.fido.ca/pages/#/my-account/view-invoice-history";
    public $username_selector = "input[name=\"email\"]";
    public $password_selector = "input#edit-customer-pwd, input[name='password']";
    public $remember_me = "input#edit-remember-me";
    public $next_button_selector = "input#edit-submit--2";
    public $submit_button_selector = 'button#customer_login';
    public $check_login_success_selector = '#myaccountProfile, a[href="/user/logout"], button[aria-label*="View account"]';
    public $billing_selector = "a[href='/myaccount/bills-statements']";
    public $more_bill_selector = ".view-more-bills-btn";
    public $login_tryout = 0;
    public $checkbox_coo = '';
    public $current_cursor = '';


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);


        // Load cookies

        $this->exts->loadCookiesFromFile();

        $this->exts->openUrl($this->baseUrl);
        $this->exts->waitTillPresent('button#onetrust-accept-btn-handler');

        $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
        sleep(10);

        $this->exts->click_by_xdotool('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]');
        sleep(2);

        $this->exts->capture_by_chromedevtool('1-init-page');


        for ($i = 0; $i < 6; $i++) {
            if (!$this->exts->exists($this->check_login_success_selector) && !$this->exts->exists("//*[text()='Menu']")) {
                $this->exts->log('NOT logged via cookie');
                $this->checkFillLogin();
                sleep(20);
                $this->check_solve_blocked_page();
            }
            // Check if the login was successful or failed
            if ($this->exts->getElement($this->check_login_success_selector) !== null ||  $this->exts->exists("//*[text()='Menu']")) {
                break;
            }
            $err_msg = "";
            if ($this->exts->exists("div.notification--error p, p.pswd-err , div[data-testid='disk-warning-parent']") != null || $this->exts->exists("p.ptrn-err-msg") || $this->exts->exists(".ptrn-msg-error") || $this->exts->exists("div[data-testid='disk-warning-parent']")) {
                $err_msg = trim($this->exts->extract("div.notification--error p, p.pswd-err,div#password-error-message"));
                if ($err_msg == "") {
                    $err_msg = trim($this->exts->extract("p.ptrn-err-msg,div#password-error-message"));
                }
                sleep(2);
                if ($err_msg == "") {
                    $err_msg = trim($this->exts->extract(".ptrn-msg-error,div#password-error-message"));
                }
            }

            if (stripos($err_msg, 'Invalid login') !== false || stripos($err_msg, "we couldn't log you in") !== false) {
                $this->exts->log("Found error message in login page : " . $err_msg);
                $this->exts->loginFailure(1);
                break;
            }
        }


        if ($this->exts->exists($this->check_login_success_selector) || $this->exts->exists("//*[text()='Menu']")) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture_by_chromedevtool("3-login-success");


            sleep(10);
            if ($this->exts->exists('a[id="Bills and payments-icon-electricity"],a[id="Bills and payments-icon-gas and electricity"]')) {
                $this->exts->click_by_xdotool('a[id="Bills and payments-icon-electricity"],a[id="Bills and payments-icon-gas and electricity"]');
            }
            sleep(5);

            if ($this->exts->exists('div[role="tablist"] > button:nth-child(02)')) {
                $this->exts->click_by_xdotool('div[role="tablist"] > button:nth-child(02)');
            }
            sleep(5);

            // Open invoices url and download invoice
            $this->invoicePage();
            $this->exts->success();
        } else {
            sleep(15);
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $err_msg = "";
            if ($this->exts->exists("div.notification--error p, p.pswd-err , div[data-testid='disk-warning-parent']") != null || $this->exts->exists("p.ptrn-err-msg") || $this->exts->exists(".ptrn-msg-error") || $this->exts->exists("div[data-testid='disk-warning-parent']")) {
                $err_msg = trim($this->exts->extract("div.notification--error p, p.pswd-err,div#password-error-message"));
                if ($err_msg == "") {
                    $err_msg = trim($this->exts->extract("p.ptrn-err-msg,div#password-error-message"));
                }
                sleep(2);
                if ($err_msg == "") {
                    $err_msg = trim($this->exts->extract(".ptrn-msg-error,div#password-error-message"));
                }
            }

            if (stripos($err_msg, 'Invalid login') !== false || stripos($err_msg, "we couldn't log you in") !== false) {
                $this->exts->log("Found error message in login page : " . $err_msg);
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function check_solve_blocked_page()
    {
        $this->exts->capture_by_chromedevtool("blocked-page-checking");

        for ($i = 0; $i < 5; $i++) {
            if ($this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
                $this->exts->refresh();
                sleep(10);

                $this->exts->click_by_xdotool('div[style="display: grid;"] > div > div', 30, 28);
                sleep(15);

                if (!$this->exts->check_exist_by_chromedevtool('div[style="display: grid;"] > div > div')) {
                    break;
                }
            } else {
                break;
            }
        }
    }



    private function checkFillLogin()
    {
        if ($this->exts->exists($this->username_selector)) {
            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
                sleep(3);
            }
            if ($this->exts->exists('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]')) {
                $this->exts->click_by_xdotool('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]');
                sleep(2);
            }
            $this->exts->capture_by_chromedevtool("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->click_by_xdotool($this->username_selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("Delete");
            sleep(1);
            $this->exts->type_text_by_xdotool($this->username);
            sleep(2);



            $this->exts->click_by_xdotool($this->password_selector);
            sleep(1);
            $this->exts->type_key_by_xdotool("Delete");
            sleep(1);
            $this->exts->type_text_by_xdotool($this->password);
            sleep(2);
            //$this->checkFillRecaptcha();
            if ($this->exts->exists('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]')) {
                $this->exts->click_by_xdotool('img.LPMcloseButton, div.LPMcontainer [src*="close_icons"]');
                sleep(2);
            }

            $this->exts->capture_by_chromedevtool("2-username-filled");
            $this->exts->click_by_xdotool('button[type="submit"]');
            sleep(15);

            //$this->checkFillRecaptcha();

        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }




    public $totalFiles = 0;
    public function invoicePage()
    {
        if ($this->exts->getElement('button[aria-label*="View account"]') != null) {
            $contracts = array();
            $conts = $this->exts->getElements('button[aria-label*="View account"]');
            foreach ($conts as $cont) {
                $accountNumber = $cont->getHtmlAttribute('aria-label');

                $this->exts->log('accountNumber:: ' . $accountNumber);

                $acc_id = trim(array_pop(explode('View account', $accountNumber)));
                $url = 'https://edfenergy.com/myaccount/accounts/' . $acc_id;
                $acc = array(
                    'url' => $url,
                    'acc_id' => $acc_id
                );

                array_push($contracts, $acc);
            }
            foreach ($contracts as $contract) {
                $this->exts->log('Goto contract url: ' . $contract['url']);
                $this->exts->log('Goto contract id: ' . $contract['acc_id']);
                $this->exts->openUrl($contract['url']);
                sleep(15);
                if ($this->exts->exists('img.LPMcloseButton')) {
                    $this->exts->click_by_xdotool("img.LPMcloseButton");
                    sleep(2);
                }

                $str = "var div = document.querySelector('var3PopOverlay'); if (div != null) {  div.style.display = \"none\"; }";
                $this->exts->execute_javascript($str);
                sleep(2);
                $this->exts->click_by_xdotool('.pane-menu-menu-left-menu  a[href*="/myaccount/bills-payments"],nav[aria-label="Section links"] > div > a[href*="bills-and-payments"]');
                sleep(15);
                if ($this->exts->exists('div[role="tablist"] button[role="tab"]:nth-child(2)')) {
                    $this->exts->click_by_xdotool('div[role="tablist"] button[role="tab"]:nth-child(2)');
                    sleep(15);
                }
                $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
                if ($restrictPages == 0 && $this->exts->exists('span:not([style="display: none;"]) a#See_more')) {
                    $this->exts->click_by_xdotool('span:not([style="display: none;"]) a#See_more');
                    sleep(15);
                }

                $this->downloadInvoice($contract['acc_id']);
            }
        } else {
            sleep(5);
            if ($this->exts->exists('img.LPMcloseButton')) {
                $this->exts->click_by_xdotool("img.LPMcloseButton");
                sleep(2);
            }

            $str = "var div = document.querySelector('var3PopOverlay'); if (div != null) {  div.style.display = \"none\"; }";
            $this->exts->execute_javascript($str);
            sleep(2);
            $this->exts->click_by_xdotool('.pane-menu-menu-left-menu  a[href*="/myaccount/bills-payments"],nav[aria-label="Section links"] > div > a[href*="bills-and-payments"]');
            sleep(15);
            if ($this->exts->exists('div[role="tablist"] button[role="tab"]:nth-child(2)')) {
                $this->exts->click_by_xdotool('div[role="tablist"] button[role="tab"]:nth-child(2)');
                sleep(15);
            }
            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0 && $this->exts->exists('span:not([style="display: none;"]) a#See_more')) {
                $this->exts->click_by_xdotool('span:not([style="display: none;"]) a#See_more');
                sleep(15);
            }
            sleep(10);
            $contract = '';
            $this->downloadInvoice($contract);
        }

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    }
    public function downloadInvoice($acc_id)
    {
        $this->exts->log("Begin download invoice ");

        $this->exts->capture('4-list-invoices');
        $receipts = $this->exts->getElements('button[data-header-text="Bill"],a[href*="/billing_statements"],a[href*=".pdf"]');
        $invoices = array();
        foreach ($receipts as $receipt) {
            if ($this->totalFiles >= 50) {
                return;
            }

            try {
                $this->exts->log('Click bills-toggle');
                $receipt->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click bills-toggle by javascript');
                $this->exts->execute_javascript("arguments[0].click()", [$receipt]);
            }
            sleep(2);

            $this->exts->wait_and_check_download('pdf');
            $downloaded_file = $this->exts->find_saved_file('pdf');

            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $invoiceFileName = basename($downloaded_file);
                $invoiceName = explode('.pdf', $invoiceFileName)[0];
                $this->exts->log('Final invoice name: ' . $invoiceName);
                $this->totalFiles++;
                // Create new invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                    sleep(1);
                }
            } else {
                $this->exts->log('Timeout when download ');
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
