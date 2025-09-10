<?php // I have migrated the script on remote Chrome and updated login forom function updated loginFailedConfirmed condition and downalod code

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

    // Server-Portal-ID: 25783 - Last modified: 24.10.2024 12:24:48 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://my.setapp.com/login";
    public $loginUrl = "https://my.setapp.com/login";
    public $restrictPages = 3;
    public $login_tryout = 0;
    public $billingPageUrl = "https://my.setapp.com/account";
    public $username_selector = "input[name='email']";
    public $password_selector = "input[name='password']";
    public $submit_button_selector = 'button[type="submit"]';
    public $logout_selector = ".log-out-link']";
    public $bill_selector = "a[href='/payment-history']";

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */


    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        sleep(2);
        $this->exts->openUrl($this->baseUrl);
        sleep(2);
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        $this->exts->loadCookiesFromFile();

        $this->exts->openUrl($this->billingPageUrl);
        sleep(15);
        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        }


        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");
            $this->exts->clearCookies();
            sleep(5);
            $this->exts->openUrl($this->baseUrl);
            sleep(5);
            $this->fillForm(0);
            sleep(5);
            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->fillForm(0);
                sleep(5);
            }
            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->fillForm(0);
                sleep(5);
            }

            if ($this->exts->querySelector($this->username_selector) != null) {
                $this->fillForm(0);
                sleep(5);
            }


            $this->exts->capture("after-login-submited");
            $this->exts->openUrl('https://my.setapp.com');
            sleep(15);

            if ($this->checkLogin()) {
                if ($this->exts->exists('button.cookie-banner__button')) {
                    $this->exts->moveToElementAndClick('button.cookie-banner__button');
                    sleep(1);
                }
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");

                $this->downloadInvoice();

                $this->exts->success();
            } else {
                $err_msg = $this->exts->extract('p.form-error');
                $this->exts->log($err_msg);
                if (stripos($err_msg, strtolower('Your email or password is incorrect')) !== false) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                } else {
                    $this->exts->capture("LoginFailed");
                    $this->exts->loginFailure();
                }
            }
        } else {
            if ($this->exts->exists('button.cookie-banner__button')) {
                $this->exts->moveToElementAndClick('button.cookie-banner__button');
                sleep(1);
            }
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->downloadInvoice();

            $this->exts->success();
        }
    }

    /**
     * Method to fill login form
     * @param Integer $count Number of times portal is retried.
     */

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);

        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            $this->exts->capture("1-login-page-filled");
            if ($this->exts->exists($this->submit_button_selector)) {
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(2);
            }
            sleep(10);

            $this->checkFillRecaptcha($count);

            if ($this->exts->exists($this->submit_button_selector)) {
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }


    private function checkFillRecaptcha($count = 1)
    {
        $this->exts->log(__FUNCTION__);
        $recaptcha_iframe_selector = 'iframe[src*="https://www.google.com/recaptcha/api2/anchor?"]';
        $recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
        $this->exts->waitTillPresent($recaptcha_iframe_selector);
        if ($this->exts->exists($recaptcha_iframe_selector)) {
            $iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
            $data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
            $this->exts->log("iframe url  - " . $iframeUrl);
            $this->exts->log("SiteKey - " . $data_siteKey);

            $isCaptchaSolved = $this->exts->processRecaptcha($this->exts->getUrl(), $data_siteKey, false);
            $this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);

            if ($isCaptchaSolved) {
                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(10);
            } else {
                if ($count < 3) {
                    $count++;
                    $this->checkFillRecaptcha($count);
                }
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Not found reCaptcha');
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

            if ($this->exts->getElement($this->bill_selector) != null) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $isLoggedIn = true;
            }
        } catch (Exception $exception) {
            $this->exts->log("Exception checking loggedin " . $exception);
        }

        return $isLoggedIn;
    }

    /**
     *method to download incoice
     */

    public $totalFiles = 0;
    function downloadInvoice()
    {
        $this->exts->log("Begin Download");
        sleep(10);
        if ($this->exts->getElement($this->bill_selector) != null) {
            $this->exts->getElement($this->bill_selector)->click();
            sleep(10);
        }



        if ($this->exts->getElement('tr.payment-history__item') != null) {
            $receipts = $this->exts->getElements('tr.payment-history__item');
            $invoices = array();
            foreach ($receipts as $i => $receipt) {
                $tags = $this->exts->getElements('td', $receipt);
                if (count($tags) >= 2 && $this->exts->getElement('a', $receipt) != null) {
                    $receiptDate = $this->exts->extract('span span', $tags[0]);
                    $receiptDate = preg_replace('/[Mm].rz/', 'Mrz', $receiptDate);
                    $receiptUrl = $this->exts->extract('a', $receipt, 'href');
                    $receiptName = trim(explode('/', end(explode('/invoices/', $receiptUrl)))[0]);
                    $receiptFileName = $receiptName . '.pdf';

                    $receiptDate = str_replace('avr', 'avril', $receiptDate);
                    $this->exts->log("receiptDate" . $receiptDate);
                    $isFrench = false;
                    foreach ($this->month_names_fr as $value) {
                        if (strpos($receiptDate, $value) !== false) {
                            $this->exts->config['lang_code'] = 'fr';
                            $isFrench = true;
                            break;
                        }
                    }

                    if ($isFrench) {
                        $receiptDate = $this->translate_date_abbr($receiptDate);
                    }

                    $parsed_date = $this->exts->parse_date($receiptDate, 'F j, Y', 'Y-m-d');
                    if ($parsed_date == "") {
                        $parsed_date = $this->exts->parse_date($receiptDate, 'M j, Y', 'Y-m-d');
                    }
                    if ($parsed_date == '') {
                        $parsed_date = $this->exts->parse_date($receiptDate, 'j. M. Y', 'Y-m-d');
                    }
                    if ($parsed_date == '') {
                        $parsed_date = $this->exts->parse_date($receiptDate, 'j. M Y', 'Y-m-d');
                    }

                    if ($parsed_date == '') {
                        $parsed_date = $this->exts->parse_date($receiptDate, 'j M Y', 'Y-m-d');
                    }

                    if ($parsed_date == '') {
                        $parsed_date = $this->exts->parse_date($receiptDate, 'j M. Y', 'Y-m-d');
                    }

                    if ($parsed_date == '') {
                        $parsed_date = $this->exts->parse_date($receiptDate, 'j M. Y', 'Y-m-d');
                    }

                    $receiptAmount = $tags[1]->getText();
                    $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' USD';

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

            //In this website OLD invoice get downloaded as PDF and NEW documents we need to print
            $newTab = $this->exts->openNewTab();
            sleep(1);
            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                $this->exts->openUrl($invoice['receiptUrl']);
                sleep(20);

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                } else {
                    $downloaded_file = $this->exts->download_current($invoice['receiptFileName'], 5);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                }
            }
            $this->exts->closeTab($newTab);
        }

        if ($this->totalFiles == 0) {
            $this->exts->log('No invoice!!');
            $this->exts->no_invoice();
        }
    }

    public     $month_names_fr = array('janv', 'fÃ©vr', 'mars', 'avril', 'mai', 'juin', 'juil', 'aoÃ»t', 'sept', 'oct', 'nov', 'dÃ©c');
    public function translate_date_abbr($date_str)
    {

        for ($i = 0; $i < count($this->month_names_fr); $i++) {
            if (stripos($date_str, $this->month_names_fr[$i]) !== FALSE) {
                $date_str = str_replace($this->month_names_fr[$i], $this->exts->month_abbr_en[$i], $date_str);
                break;
            }
        }
        return $date_str;
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
