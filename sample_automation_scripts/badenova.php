<?php // handle empty invoice name case updated download code

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

    // Server-Portal-ID: 75330 - Last modified: 10.06.2025 09:11:30 UTC - User: 1

    public $baseUrl = "https://shop.badenova.de/account";
    public $loginUrl = "https://shop.badenova.de/account";
    public $homePageUrl = "https://shop.badenova.de/account";
    public $username_selector = 'input#login, input#email';
    public $password_selector = 'input#password';
    public $submit_button_selector = 'div.widget-login form button[type="submit"], form[action*="user"] button[type*="submit"]';
    public $login_tryout = 0;
    public $restrictPages = 3;



    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");



        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->homePageUrl);
            sleep(15);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");


            if ($this->exts->exists('div[class*="banner tc-privacy-popin"] button[id*="popin"]')) {
                $this->exts->log("Accept cookie");
                $this->exts->moveToElementAndClick('div[class*="banner tc-privacy-popin"] button[id*="popin"]');
                sleep(15);
            }

            $this->exts->moveToElementAndClick('div[class*="header"]  div[class*="login"] a[href*="account"]');
            sleep(5);
            $this->fillForm(0);
            sleep(10);
            if ($this->exts->exists('div[class*="SurveyInvitation"]')) {
                $this->exts->log("Close survey");
                $this->exts->moveToElementAndClick('div[class*="SurveyInvitation"] button[class*="close"]');
                sleep(15);
            }
            if ($this->exts->exists('div[class*="banner tc-privacy-popin"] button[id*="popin"]')) {
                $this->exts->log("Accept cookie");
                $this->exts->moveToElementAndClick('div[class*="banner tc-privacy-popin"] button[id*="popin"]');
                sleep(15);
            }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {
                if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                    $this->exts->log('ONLY EMAIL NEEDED AS USERNAME');
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure();
                }
            }
        } else {
            sleep(10);
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        }
    }

    function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            if ($this->exts->exists($this->username_selector) || $this->exts->exists($this->password_selector)) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                // $this->exts->moveToElementAndClick('div.widget-login form a[onclick*="javascript:performLogin"]');
                sleep(10);

                $err_msg = $this->exts->extract('.form-field--password div.form-error__message');

                if ($err_msg != "" && $err_msg != null && strpos(strtolower($err_msg), 'passwor') !== false) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                }
            }

            sleep(10);
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
            if ($this->exts->exists('a#logout, a[href*="/logout"]') && !$this->exts->exists($this->password_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    function invoicePage()
    {
        $this->exts->log("Invoice page");
        if ($this->exts->exists('button#accept-recommended-btn-handler')) {
            $this->exts->moveToElementAndClick('button#accept-recommended-btn-handler');
            sleep(5);
        }
        if ($this->exts->exists('div#accordionMenu div#accordion-dropdown-installation a')) {
            $accounts = $this->exts->getElements('div#accordionMenu div#accordion-dropdown-installation a');
            $accounts_len = count($accounts);
            $accounts_types = $this->exts->getElements('div#accordion-dropdown-meter a');
            $accounts_types_len = count($accounts_types);

            for ($i = 0; $i < $accounts_len; $i++) {
                $this->exts->openUrl('https://meine-badenova.badenova.de/powercommerce/bn/action/flightdeck');
                sleep(15);
                $accounts = $this->exts->getElements('div#accordionMenu div#accordion-dropdown-installation a');
                $acc_id = $accounts[$i]->getAttribute('tobj');
                $acc_sel = 'div#accordionMenu div#accordion-dropdown-installation a[tobj="' . $acc_id . '"]';

                $this->exts->moveToElementAndClick($acc_sel);
                sleep(5);

                for ($j = 0; $j < $accounts_types_len; $j++) {
                    $this->exts->openUrl('https://meine-badenova.badenova.de/powercommerce/bn/action/flightdeck');
                    sleep(15);
                    $accounts_types = $this->exts->getElements('div#accordion-dropdown-meter a');
                    $acc_type_id = $accounts_types[$j]->getAttribute('tobj');
                    $acc_type_sel = 'div#accordion-dropdown-meter a[tobj="' . $acc_type_id . '"]';
                    $this->exts->moveToElementAndClick($acc_type_sel);
                    sleep(5);

                    $this->exts->moveToElementAndClick('div.postbox div[container-class="postbox"] a[onclick*="/powercommerce/bn/action/postbox"]');
                    sleep(15);

                    $this->downloadInvoice($acc_id, $acc_type_id);
                }
            }
        } else {
            $this->exts->moveToElementAndClick('nav a[href*="customer_self_service/messages"]');
            $this->processInvoices();
        }

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    function downloadInvoice($acc_id = '', $acc_type_id = '')
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->getElement('table#pageable-dms tbody tr') != null) {
                $receipts = $this->exts->getElements('table#pageable-dms tbody tr');
                $invoices = array();
                foreach ($receipts as $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) >= 3 && $this->exts->getElement('td a', $receipt) != null) {
                        $receiptDate = $tags[0]->getAttribute('innerText');
                        $receiptUrl = $this->exts->extract('td a', $receipt, 'href');
                        $receiptName = $acc_id . $acc_type_id . trim(str_replace('.', '', $receiptDate));
                        $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                        $receiptAmount = '';

                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice URL: " . $receiptUrl);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("Invoice parsed_date: " . $parsed_date);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $invoice = array(
                            'receiptName' => $receiptName,
                            'receiptUrl' => $receiptUrl,
                            'parsed_date' => $parsed_date,
                            'receiptAmount' => $receiptAmount,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log("Invoice found: " . count($invoices));

                foreach ($invoices as $invoice) {
                    $this->totalFiles += 1;
                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }

    private function processInvoices()
    {
        sleep(2);
        for ($i = 0; $i < 20 && $this->exts->getElement('div.inbox-list div.inbox-list__item div.inbox-message__title') == null; $i++) {
            sleep(1);
        }
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('div.inbox-list div.inbox-list__item');

        $this->exts->log('Total Rows:: ' . count($rows));

        foreach ($rows as $row) {

            if (stripos($this->exts->extract('div.inbox-message__title', $row, 'innerText'), 'Rechnung') !== false) {
                $invoiceName = '';
                $invoiceDate = trim($this->exts->extract('.inbox-message__date', $row, 'innerText'));

                try {
                    $this->exts->log('Click row button');
                    $row->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click row button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$row]);
                }
                sleep(2);
                for ($i = 0; $i < 10 && $this->exts->getElement('.show a.inbox-message-attachement__title') == null; $i++) {
                    sleep(1);
                }
                $download_buttons = $this->exts->getElements('.show a.inbox-message-attachement__title');
                foreach ($download_buttons as $key => $download_button) {
                    $download_button_text = trim($download_button->getAttribute('innerText'));
                    $invoiceUrl = $download_button->getAttribute("href");
                    if (preg_match('/AI-[A-Z0-9]+-[A-Z0-9]+/', $invoiceUrl, $matches)) {
                        $invoiceName = $matches[0];
                    }

                    if (stripos($download_button_text, '.pdf') !== false) {
                        array_push($invoices, array(
                            'invoiceName' => $invoiceName,
                            'invoiceDate' => $invoiceDate,
                            'invoiceAmount' => '',
                            'invoiceUrl' => $invoiceUrl,
                        ));
                        $this->isNoInvoice = false;
                        break;
                    }
                }

                if ($this->exts->exists('.show .modal-actions button.gtm-inbox-show-close-button')) {
                    $this->exts->moveToElementAndClick('.show .modal-actions button.gtm-inbox-show-close-button');
                    sleep(5);
                }
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
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
