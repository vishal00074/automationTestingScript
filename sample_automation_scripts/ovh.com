<?php // handle emapty invoice name case 

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
    // Server-Portal-ID: 24117 - Last modified: 12.05.2025 14:10:58 UTC - User: 1

    public $baseUrl = 'https://www.ovh.com/manager/dedicated/index.html';
    public $loginUrl = 'https://www.ovh.com/auth/?action=disconnect&onsuccess=https%3A%2F%2Fwww.ovh.com%2Fmanager%2Fdedicated%2Findex.html%23%2Fbilling%2Fhistory';
    public $invoicePageUrl = 'https://www.ovh.com/manager/dedicated/index.html#/billing/history';

    public $username_selector = 'input#account';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_btn = 'button#login-submit';

    public $checkLoginFailedSelector = 'div.error';
    public $checkLoggedinSelector = 'li.logout button, button[data-translate="hub_user_logout"], button[data-navi-id="logout"], [href*="/dedicated/billing/history"]';

    public $twoFactorInputSelector = 'form input#totp, form input#emailCode, form input#codeSMS, input#staticOTP';
    public $submit_twofactor_btn_selector = 'form button[id*="Submit"]';

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
        $this->exts->capture("Home-page-without-cookie");

        // Load cookies
        // $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(5);

        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            // If user has logged in via cookies, call waitForLogin
            $this->exts->log('Logged in from initPortal');
            $this->exts->capture('0-init-portal-loggedin');
            $this->waitForLogin();
        } else {
            // If user hase not logged in, open the login url and wait for login form
            $this->exts->log('NOT logged in from initPortal');
            $this->exts->capture('0-init-portal-not-loggedin');
            $this->exts->clearCookies();

            $this->exts->openUrl($this->loginUrl);
            $this->waitForLoginPage();
            $check_2fa = strtolower($this->exts->extract('.mfa-container', null, 'innerText'));
            $this->exts->log($check_2fa);
            if ($this->exts->exists('button.accept')) {
                $this->exts->moveToElementAndClick('button.accept');
            }
            $check_2fa = strtolower($this->exts->extract('.mfa-container', null, 'innerText'));
            $this->exts->log($check_2fa);
            if (stripos($check_2fa, 'your security key') !== false) {
                $this->exts->moveToElementAndClick('.other-method a#other-method-link');
                sleep(15);
            }

            // if ($this->exts->exists('div[data-mfa-type*="sms"]')) {
            //     $this->exts->moveToElementAndClick('div[data-mfa-type*="sms"]');
            //     sleep(5);
            //     $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
            //     sleep(15);
            // } 

            if ($this->exts->exists('div[data-mfa-type**="staticOTP"]')) {
                $this->exts->moveToElementAndClick('div[data-mfa-type**="staticOTP"]');
                sleep(7);
                $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
                sleep(15);
            } else if ($this->exts->exists('div[data-mfa-type*="mail"]')) {
                $this->exts->moveToElementAndClick('div[data-mfa-type*="mail"]');
                sleep(6);
                $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
                sleep(15);
            } else if ($this->exts->exists('div[data-mfa-type*="totp"]')) {
                $this->exts->moveToElementAndClick('div[data-mfa-type*="totp"]');
                sleep(6);
                $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
                sleep(15);
            } else if ($this->exts->exists('div[data-mfa-type**="staticOTP"]')) {
                $this->exts->moveToElementAndClick('div[data-mfa-type**="staticOTP"]');
                sleep(7);
                $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
                sleep(15);
            } else if ($this->exts->exists($this->twoFactorInputSelector)) {
                $this->fillTwoFactor($this->twoFactorInputSelector, $this->submit_twofactor_btn_selector);
                sleep(15);
            }

            $this->waitForLogin();
        }
    }

    private function waitForLoginPage()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("1-pre-login");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-filled-login");
            if ($this->exts->exists($this->submit_login_btn)) {
                $this->exts->moveToElementAndClick($this->submit_login_btn);
                sleep(5);
            }
            sleep(10);
        } else {
            $this->exts->log('Timeout waitForLoginPage');
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }

    private function waitForLogin()
    {
        $this->exts->waitTillPresent($this->checkLoggedinSelector, 20);
        if ($this->exts->getElement($this->checkLoggedinSelector) != null) {
            $this->exts->log('User logged in.');
            $this->exts->capture("2-post-login");

            // Open invoices url
            $this->exts->openUrl($this->invoicePageUrl);
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log('Timeout waitForLogin');
            $this->exts->capture("LoginFailed");
            if (stripos($this->exts->extract($this->checkLoginFailedSelector, null, 'innerText'), "Invalid Account ID or password") !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function fillTwoFactor($twoFactorInputSelector, $submit_twofactor_btn_selector)
    {
        $two_factor_selector = $twoFactorInputSelector;
        $two_factor_message_selector = 'form[method="POST"] div.control-group:first-child, div#enter2FA > div[style*="text-align: left"], div.login-inputs form > div  div.control-group:first-child';
        $two_factor_submit_selector = $submit_twofactor_btn_selector;

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getText() . "\n";
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

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);
                $this->exts->capture("after-submit-2fa");

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                    $this->exts->capture("post-login-2fa");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->fillTwoFactor($twoFactorInputSelector, $submit_twofactor_btn_selector);
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function processInvoices($paging_count = 1)
    {
        sleep(10);
        $this->exts->log('Invoices found');
        $this->exts->capture("4-page-opened");
        $limit = 25;
        if ((int)@$this->exts->config_array["restrictPages"] == 0)
            $limit = 500;
        $this->exts->switchToFrame('iframe[role="document"]');
        $invoices = [];
        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 9 && $this->exts->getElement('a[href*="order/bill.pdf"]', $tags[8]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="order/bill.pdf"]', $tags[8])->getAttribute("href");
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceDate = trim($tags[3]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' EUR';

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
            $date_parse = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
            if ($date_parse == '') {
                $date_parse = $this->exts->parse_date($invoice['invoiceDate'], 'j M Y', 'Y-m-d');
            }

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $date_parse, $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $this->exts->switchToFrame('iframe[role="document"]');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if (
            $restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('button.oui-pagination-nav__next:not([disabled="disabled"])') != null
        ) {
            $paging_count++;
            $this->exts->moveToElementAndClick('button.oui-pagination-nav__next:not([disabled="disabled"])');
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
