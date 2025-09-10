<?php // migrated

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

    // Server-Portal-ID: 9589 - Last modified: 29.07.2024 14:14:04 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://account.shareasale.com/a-revenuereport.cfm';
    public $loginUrl = 'https://account.shareasale.com/a-revenuereport.cfm';
    public $invoicePageUrl = 'https://account.shareasale.com/a-revenuereport.cfm';

    public $username_selector = 'div#sas-outer form#form1 input#username';
    public $password_selector = 'div#sas-outer form#form1 input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div#sas-outer form#form1 div.loginBox-cmd button[type="submit"]';

    public $check_login_failed_selector = 'div.loginFailMsg';
    public $check_login_success_selector = 'a[href*="/a-logoff.cfm"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->check_solve_blocked_page();
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->check_solve_blocked_page();
            $this->checkFillLogin();
            sleep(20);
            $this->check_solve_blocked_page();
        }

        $tries = 0;
        while ($this->exts->exists('form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]') && $tries < 3) {
            $this->checkCaptcha($tries);
            sleep(10);
            $tries++;
        }
        if ($this->exts->exists('.password-new input[name="newPass"]')) {
            $this->exts->capture('account-must-change_password');
            $this->exts->account_not_ready();
        }

        if ($this->exts->exists('form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]')) {
            $this->exts->log(__FUNCTION__ . '::captcha not resolved after 3 tries');
            $this->exts->loginFailure();
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);
            // if($this->exts->exists('form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]')){
            // 	$this->checkCaptcha();
            // 	sleep(10);
            // }
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('input[src*="GoToMyAccount"]') && $this->exts->exists('form[action="completeverification.cfm"]')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    function checkCaptcha($count)
    {
        $this->exts->log("Begin checkCaptcha " . $count);
        try {

            if ($this->exts->getElement('form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]') != null) {
                $this->exts->capture("1-checkCaptcha-" . $count);

                $this->exts->processCaptcha('img[src*="captchaImage"]', 'form[action*="a-solveCaptcha.cfm"] input[name="affCaptchaSolution"]');
                sleep(5);
                $this->exts->moveToElementAndClick('input[type="submit"]');
                sleep(5);
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception checkCaptcha " . $exception->getMessage());
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


    private function processInvoices()
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 4 && $this->exts->getElement('a[href*="a-paymentStatement"]', $tags[3]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="a-paymentStatement"]', $tags[3])->getAttribute("href");
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceName = trim(str_replace(',', '-', str_replace(' ', '', $invoiceDate)));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' USD';

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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'F, Y', 'Y-m-01');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
