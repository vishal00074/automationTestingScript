<?php // migrated and added loginFailedConfirmed condition add check login funcion and handle empty invoice name case

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

    public $baseUrl = 'https://secure.zervant.com';
    public $loginUrl = 'https://secure.zervant.com/login/';
    public $invoicePageUrl = '';

    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'div.Button';

    public $check_login_failed_selector = 'div.error';
    public $check_login_success_selector = 'a#logout-tab, a[data-automation="mainmenu-logout"]';

    public $isNoInvoice = true;
    public $only_incoming_invoice = 0;
    public $only_outgoing_invoice = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->only_outgoing_invoice = isset($this->exts->config_array["only_outgoing_invoice"]) ? (int)@$this->exts->config_array["only_outgoing_invoice"] : $this->only_outgoing_invoice;
        $this->only_incoming_invoice = isset($this->exts->config_array["only_incoming_invoice"]) ? (int)@$this->exts->config_array["only_incoming_invoice"] : $this->only_incoming_invoice;

        $this->exts->log('only_outgoing_invoice '.   $this->only_outgoing_invoice);
        $this->exts->log('only_incoming_invoice '.   $this->only_incoming_invoice);
        $this->exts->capture('1-init-page');

        $this->exts->openUrl($this->baseUrl);
        sleep(1);
        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(5);
        $this->exts->capture('1-init-page');

        $this->exts->refresh();
        sleep(2);
        $this->exts->refresh();

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            // if cookie expired then can not login
            $this->exts->clearCookies();
            sleep(2);
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->checkFillLogin();
            sleep(5);
            $this->checkFillTwoFactor();
            sleep(5);
            $this->exts->refresh();
            sleep(2);
            $this->exts->refresh();
        }


        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            if ($this->only_outgoing_invoice == 1) {
                $this->exts->moveToElementAndClick('div.left a#reactInvoiceList-tab');
                $this->processInvoices();
            }

            if ($this->only_outgoing_invoice == 0 || $this->only_incoming_invoice == 1) {
                $this->exts->openUrl('https://secure.zervant.com/myAccount/myPlan');
                sleep(2);
                $this->exts->refresh();
                sleep(2);
                $this->exts->refresh();
                sleep(7);
                $this->exts->waitTillPresent('button[data-automation="MyPlanPage-header-tab-recipes"]');
                $this->exts->moveToElementAndClick('button[data-automation="MyPlanPage-header-tab-recipes"]');
                $this->processReceipts();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $logged_in_failed_selector = $this->exts->getElementByText($this->check_login_failed_selector, ['password', 'Passwort'], null, false);

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if ($logged_in_failed_selector != null) {
                $this->exts->loginFailure(1);
            } else  if (
                stripos($error_text, strtolower('The email address you entered is incorrect')) !== false ||
                stripos($error_text, strtolower('Incorrect username or password')) !== false
            ) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#code';
        $two_factor_message_selector = '.confirm-mfa .confirm-mfa__text';
        $two_factor_submit_selector = '.confirm-mfa input#code + .Button';

        $this->exts->waitTillPresent($two_factor_selector);

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

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
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
    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->password_selector);
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

    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public  function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
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

    private function processInvoices()
    {
        sleep(25);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('.zervant-list-rows div.zervant-list-row:not(.zervant-list-segment-sumary)');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('.zervant-list-rowcell', $row);
            if (count($tags) >= 7) {
                try {
                    $this->exts->log('Click row button');
                    $row->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click row button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$row]);
                }
                sleep(10);
                if ($this->exts->exists('a[download*=".pdf"]')) {
                    $invoiceUrl = $this->exts->getElement('a[download*=".pdf"]')->getAttribute("href");
                } else {
                    continue;
                }
                $invoiceName = trim($tags[0]->getAttribute('innerText'));
                $invoiceDate = trim($tags[2]->getAttribute('innerText'));
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
            $parse_date = $this->exts->parse_date($invoice['invoiceDate'], 'j F Y', 'Y-m-d');
            if ($parse_date == '') {
                $parse_date = $this->exts->parse_date($invoice['invoiceDate'], 'j.m.Y', 'Y-m-d');
            }
            $this->exts->log('Date parsed: ' . $parse_date);


            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $parse_date, $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processReceipts()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div.zervant-list-row');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('div.zervant-list-rowcell', $row);
            if (count($tags) >= 6 && $this->exts->getElement('a[href*="invoice.stripe.com"]', $tags[5]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="invoice.stripe.com"]', $tags[5])->getAttribute("href");
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';

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
            $newTab = $this->exts->openNewTab();
            sleep(2);

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(10);

            $this->exts->moveToElementAndClick('.InvoiceDetails-table button:nth-child(1)');
            sleep(25);

            $this->exts->wait_and_check_download('pdf');

            $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->exts->closeTab($newTab);
            sleep(2);
            $this->isNoInvoice = false;
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
