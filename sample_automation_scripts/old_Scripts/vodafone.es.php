<?php //  migrated and updated login code. added twofa code

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

    // Server-Portal-ID: 8747 - Last modified: 12.01.2024 15:39:53 UTC - User: 1

    public $baseUrl = "https://www.vodafone.es/empresas/es/login-ds/";
    public $loginUrl = "https://www.vodafone.es/c/particulares/es/acceso-area-privada/";
    public $homePageUrl = "https://www.vodafone.es/c/mivodafone/es/area-privada-contrato";
    public $username_selector = 'form#loginFormPage input[name="uuid"], form[name="loginForm"] input#userid, input#ManualLoginComp_txt_username';
    public $password_selector = 'form#loginFormPage input[name="password"], form[name="loginForm"] input#password, input#ManualLoginComp_txt_password';
    public $submit_button_selector = 'form#loginFormPage input#loginButton, form[name="loginForm"] input#enter, button#ManualLoginComp_btn_submitLogin';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $check_login_success_selector = 'a[data-ng-click="vm.logout()"]';



    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->exts->capture("Home-page-without-cookie");
        $this->exts->loadCookiesFromFile();

        if ($this->exts->exists('button[id="onetrust-accept-btn-handler"]')) {
            $this->exts->moveToElementAndClick('button[id="onetrust-accept-btn-handler"]');
            sleep(5);
        }

        if (!$this->checkLogin()) {

            $this->clearChrome();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->exts->waitTillPresent('button[id="onetrust-accept-btn-handler"]', 7);
            if ($this->exts->exists('button[id="onetrust-accept-btn-handler"]')) {
                $this->exts->moveToElementAndClick('button[id="onetrust-accept-btn-handler"]');
                sleep(5);
            }

            $this->fillForm(0);

            $this->checkFillTwoFactor();


            $twoFaError = strtolower($this->exts->extract('span[class*="text-field__helperText"]'));

            if (strpos($twoFaError, 'incorrecto') !== false) {
                $this->exts->log($twoFaError);
                $this->exts->moveToElementAndClick('p.otp-link');
                sleep(7);
                $this->checkFillTwoFactor();
            }
        }


        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            $this->exts->capture("LoginFailed");
            $err_msg = strtolower($this->exts->extract('sp-alert-info#loginManualLoginAlertInfo b'));

            $twoFaError = strtolower($this->exts->extract('span[class*="text-field__helperText"]'));

            if (strpos($err_msg, 'incorrectos') !== false) {
                $this->exts->log($err_msg);
                $this->exts->loginFailure(1);
            } else if (strpos($twoFaError, 'incorrecto') !== false) {
                $this->exts->log($twoFaError);
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function clearChrome()
    {
        $this->exts->log("Clearing browser history, cookie, cache");
        $this->exts->openUrl('chrome://settings/clearBrowserData');
        sleep(10);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 2; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Tab');
        $this->exts->type_key_by_xdotool('Return');
        $this->exts->type_key_by_xdotool('a');
        sleep(1);
        $this->exts->type_key_by_xdotool('Return');
        sleep(3);
        $this->exts->capture("clear-page");
        for ($i = 0; $i < 5; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
        }
        $this->exts->type_key_by_xdotool('Return');
        sleep(15);
        $this->exts->capture("after-clear");
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(5);
            $this->exts->waitTillPresent($this->username_selector);
            if ($this->exts->exists($this->username_selector) || $this->exts->exists($this->password_selector)) {
                sleep(2);

                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(2);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(10);

                if ($this->exts->querySelector('div.otp-info-container div[class*="otp-inputs"]:nth-child(3) input[type="radio"]') != null) {
                    $this->exts->moveToElementAndClick('div.otp-info-container div[class*="otp-inputs"]:nth-child(3) input[type="radio"]');
                    sleep(5);
                }
                $this->exts->moveToElementAndClick('button#ManualLoginComp_btn_submitOtp');
                sleep(10);


                if ($this->exts->querySelector('div.modal-buttons button#btn_primaryClick') != null) {
                    $this->exts->moveToElementAndClick('div.modal-buttons button#btn_primaryClick');
                    sleep(10);
                    $this->exts->moveToElementAndClick('button#ManualLoginComp_btn_submitOtp');
                    sleep(10);
                }
            }

            // sleep(10);
            // $this->exts->openUrl($this->homePageUrl);
            // sleep(15);

            // $this->exts->moveToElementAndClick('button[data-ng-click="vm.goFacturas()"]');
            // sleep(15);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }


    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'div.otp-form input';
        $two_factor_message_selector = 'span[class*="text-field__helperText"]';
        $two_factor_submit_selector = 'button#ManualLoginComp_btn_submitCodeOtp';


        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            $this->exts->notification_uid = '';

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('innerText') . "\n";
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

                if ($this->exts->getElement('div.private-checkbox.remember-device input') != null) {
                    $this->exts->moveToElementAndClick('div.private-checkbox.remember-device input');
                    sleep(2);
                }
                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);
                if ($this->exts->exists('button[data-2fa-rememberme="true"]')) {
                    $this->exts->moveToElementAndClick('button[data-2fa-rememberme="true"]');
                    sleep(15);
                }
                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $this->check_login_success_selector . "');") != 1; $wait++) {
                $this->exts->log('Waiting for login.....');
                sleep(10);
            }
            if ($this->exts->exists($this->check_login_success_selector) && !$this->exts->exists($this->password_selector)) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }
        return $isLoggedIn;
    }

    private function invoicePage()
    {
        $this->exts->log("Invoice page");

        $this->exts->openUrl($this->homePageUrl);
        sleep(15);

        $this->exts->moveToElementAndClick('button[data-ng-click="vm.goFacturas()"]');
        sleep(15);

        $this->exts->moveToElementAndClick('a[href="#/mis-facturas"]');
        sleep(15);

        $this->downloadInvoice1();

        $this->exts->openUrl('https://www.vodafone.es/areaclientes/empresas/es/facturacion/?ebplink=/tbmb-xt/invoice/history.do');
        sleep(15);

        $this->downloadInvoice();

        if ($this->totalFiles == 0) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
    }

    public $totalFiles = 0;
    private function downloadInvoice()
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->getElement('table > tbody tr a.pdfDownloadLink') != null) {
                $receipts = $this->exts->getElements('table > tbody tr');
                $invoices = array();
                foreach ($receipts as $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) >= 4 && $this->exts->getElement('a.pdfDownloadLink', $receipt) != null) {
                        $receiptDate = trim($tags[0]->getText());
                        $receiptUrl = 'table > tbody tr a#' . $this->exts->extract('a.pdfDownloadLink', $receipt, 'id');
                        $receiptName = trim($tags[1]->getText());
                        $receiptFileName = $receiptName . '.pdf';
                        $parsed_date = $this->exts->parse_date($receiptDate, 'd-m-Y', 'Y-m-d');
                        $receiptAmount = trim($tags[2]->getText());
                        $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';

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
                    if ($this->exts->getElement($invoice['receiptUrl']) != null) {
                        if ($this->exts->document_exists($invoice['receiptFileName'])) {
                            continue;
                        }

                        $this->exts->moveToElementAndClick($invoice['receiptUrl']);

                        $this->exts->wait_and_check_download('pdf');

                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                        sleep(1);

                        if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                            $this->exts->log("create file");
                            $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                        }
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
        }
    }

    private function downloadInvoice1()
    {
        $this->exts->log("Begin download invoice1");

        $this->exts->capture('4-List-invoice1');

        $receipts = $this->exts->getElements('mvf-invoice-histogram[id-factura-histograma] ul.mvf-chart-bars__labels li');
        $invoices = array();
        foreach ($receipts as $i => $receipt) {
            $receiptAmount = trim($this->exts->extract('span.mvf-chart-bars__labels-item-amount', $receipt));
            $this->exts->log('receiptAmount: ' . $receiptAmount);

            if (strpos($receiptAmount, '0,00') === false && $receiptAmount != '') {
                $receiptDate = $this->exts->extract('span.mvf-chart-bars__labels-item-title', $receipt);
                $receiptUrl = $receipt;
                $this->exts->webdriver->executeScript(
                    "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                    array($receiptUrl, $i)
                );

                $receiptUrl = 'mvf-invoice-histogram[id-factura-histograma] ul.mvf-chart-bars__labels li#invoice' . $i;
                $receiptName = '';
                $receiptFileName = $receiptName . '.pdf';
                $parsed_date = '';
                $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';

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
                    'receiptDate' => $receiptDate,
                    'receiptAmount' => $receiptAmount,
                    'receiptFileName' => $receiptFileName
                );
                array_push($invoices, $invoice);
            }
        }

        $this->exts->log("Invoice found: " . count($invoices));

        foreach ($invoices as $invoice) {
            $this->totalFiles += 1;

            $receipts = $this->exts->getElements('mvf-invoice-histogram[id-factura-histograma] ul.mvf-chart-bars__labels li');
            foreach ($receipts as $i => $receipt) {
                $receiptAmount = trim($this->exts->extract('span.mvf-chart-bars__labels-item-amount', $receipt));

                if (strpos($receiptAmount, '0,00') === false && $receiptAmount != '') {
                    $receiptUrl = $receipt;
                    $this->exts->webdriver->executeScript(
                        "arguments[0].setAttribute(\"id\", \"invoice\" + arguments[1]);",
                        array($receiptUrl, $i)
                    );
                }
            }

            if ($this->exts->getElement($invoice['receiptUrl']) != null) {
                $this->exts->moveToElementAndClick($invoice['receiptUrl']);
                sleep(15);

                // click and download invoice
                // mvf-icon[shape*="documentPdf"]
                if ($this->exts->exists('.mvf-header-body__download-invoice span[data-ng-click*="descargas"], mvf-icon[shape*="documentPdf"]')) {
                    $this->exts->moveToElementAndClick('.mvf-header-body__download-invoice span[data-ng-click*="descargas"], mvf-icon[shape*="documentPdf"]');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceFileName = basename($downloaded_file);
                        $invoiceName = explode('.pdf', $invoiceFileName)[0];
                        $invoiceName = trim(explode('(', $invoiceName)[0]);
                        $this->exts->log('Final invoice name: ' . $invoiceName);

                        // Call new_invoice if it not existed
                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $this->exts->new_invoice($invoiceName, '', $receiptAmount, $invoiceFileName);
                            sleep(1);
                        }
                    } else {
                        $this->exts->log('Timeout when download ');
                    }
                }
            }
        }
    }

    public $month_es_array = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'];
    private function translate_date($str)
    {
        foreach ($this->month_es_array as $i => $month_es) {
            $this->exts->log($month_es);
            $this->exts->log($str);
            if ($month_es == $str) {
                $this->exts->log('compare');
                return $this->exts->month_abbr_en[$i];
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
