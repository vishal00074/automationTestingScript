<?php // 

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

    // Server-Portal-ID: 6540 - Last modified: 13.03.2025 16:49:02 UTC - User: 15
    // start script 

    public $baseUrl = 'https://www.papierkram.de/';
    public $loginUrl = 'https://www.papierkram.de/login/';

    public $username_selector = 'form[action="/login"] input#user_email , form[action="/login"] input#user_new_email';
    public $password_selector = 'form[action="/login"] input#user_new_password, form[action="/login"] input#user_password';
    public $remember_me_selector = 'form[action="/login"] input#user_remember_me';
    public $submit_login_selector = 'form[action="/login"] input[type="submit"]';

    public $check_login_failed_selector = 'form[action="/login/"] span.text-danger';
    public $check_login_success_selector = 'a[href="/logout"]';
    public $subDomain_selector = '#loginRedirectForm input[name="subdomain"] , form[action="/login/"] input[name="subdomain"]';
    public $submit_subDomain = '#loginRedirectForm button[type="submit"]';

    public $isNoInvoice = true;
    public $subDomain = '';

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        $this->subDomain = $this->exts->config_array["subdomain"];
        $this->exts->log('subDomain:: ' . $this->subDomain);
        // $this->subDomain = str_replace('.papierkram.de', '', $this->subDomain);
        // $this->exts->log('subDomain:: ' . $this->subDomain);

        if ($this->subDomain == '') {
            $this->exts->loginFailure(1);
        }
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            // $loginUrl = 'https://'.$this->exts->config_array["subdomain"].'.papierkram.de/login';
            // $this->exts->openUrl($loginUrl);
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            if ($this->exts->exists('div#consent_manager-wrapper a.consent_manager-accept-all')) {
                $this->exts->moveToElementAndClick('div#consent_manager-wrapper a.consent_manager-accept-all');
                sleep(1);
            }
            $this->checkFillLogin();
            sleep(20);

            if ($this->exts->exists('form[action="/login/email_code"]')) {
                $this->checkFillTwoFactor();
            }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $invoicePageUrl = 'https://' . $this->subDomain . '.papierkram.de/einnahmen/rechnungen';
            $this->exts->openUrl($invoicePageUrl);
            sleep(20);

            $this->exts->moveToElementAndClick('div.more-filters');
            sleep(5);

            $this->exts->moveToElementAndClick('div.dropdown-menu  a input[value="year_all"]');
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'domain ist leider ung') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->exists('form[action="/einstellungen/neujahr"]')) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->querySelector($this->password_selector) != null || $this->exts->querySelector($this->subDomain_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->openUrl($this->subDomain);
            sleep(10);
            $this->exts->waitTillPresent($this->username_selector);

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me_selector != '') {
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            }
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);

            // $this->exts->waitForCssSelectorPresent('div.toast-error div.toast-message', function() {
            // 	$this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Found login failed screen!!!! ");
            // 	if(stripos(strtolower($this->exts->extract('div.toast-error div.toast-message')), 'anmeldedaten oder sie sind deaktiviert') !== false) {
            // 		$this->exts->loginFailure(1);
            // 		sleep(5);
            // 	}

            // }, function() {
            // 	$this->exts->log(__FUNCTION__ . "::>>>>>>>>>>>>>>>Timed out waiting for login failure message");
            // }, 15);
            $this->exts->waitTillPresent('div.toast-error div.toast-message', 5);
            if (stripos(strtolower($this->exts->extract('div.toast-error div.toast-message')), 'anmeldedaten oder sie sind deaktiviert') !== false) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#user_email_code_attempt';
        $two_factor_message_selector = 'form[action="/login/email_code"] p:nth-child(03)';
        $two_factor_submit_selector = 'input[name="commit"]';
        $two_factor_resend_selector = 'a#re-send-link';

        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);

            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en .= ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de .= ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }

            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());

            if (!empty($two_factor_code)) {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code: " . $two_factor_code);
                $this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                sleep(1);

                if ($this->exts->exists($two_factor_resend_selector)) {
                    $this->exts->moveToElementAndClick($two_factor_resend_selector);
                    sleep(1);
                }

                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor cannot be solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 9  && $this->exts->getElement('a[href*="/rechnungen"]', $tags[3]) != null && $this->exts->getElement('span.label-success', $tags[5]) != null) {

                $invoicelink = $this->exts->getElement('a[href*="/rechnungen"]', $tags[3])->getAttribute("href");
                $invoiceUrl = $invoicelink . '.pdf';
                $invoiceName = explode(
                    'rechnungen/',
                    array_pop(explode('/', $invoiceUrl))
                )[0];
                $invoiceDate = trim($tags[6]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('div.meta-info', $tags[8]))) . ' EUR';

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
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $paging_count++;
        if (
            $this->exts->config_array["restrictPages"] == '0' &&
            $paging_count < 50 &&
            $this->exts->getElement('nav.pagination a[rel="next"] i.icon-angle-right') != null
        ) {
            $this->exts->moveToElementAndClick('nav.pagination a[rel="next"] i.icon-angle-right');
            sleep(5);
            $this->processInvoices($paging_count);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
