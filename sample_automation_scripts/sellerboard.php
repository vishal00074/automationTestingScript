<?php //  migrated and updated pagination logic

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

    // Server-Portal-ID: 78244 - Last modified: 21.03.2024 14:10:10 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = "https://app.sellerboard.com/en/dashboard/";
    public $loginUrl = "https://app.sellerboard.com/en/auth/login";
    public $username_selector = 'input#username';
    public $password_selector = 'input#password';
    public $submit_button_selector = 'button.btn-lg';
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
        sleep(10);
        $this->exts->capture("Home-page-without-cookie");
        $this->exts->loadCookiesFromFile();
        if (!$this->checkLogin()) {
            sleep(10);
            $this->fillForm(0);
            sleep(12);
            $this->checkFillTwoFactor();
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->invoicePage();

            if ($this->totalFiles == 0) {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else if ($this->exts->getElementByText('.login-container.restart .panel-heading', ['account deactivated', 'Konto deaktiviert'], null, false) != null) {
            $this->exts->log("account not ready");
            $this->exts->account_not_ready();
        } else if (strpos(strtolower($this->exts->extract('div.login-form')), 'login error') !== false) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('#tooManyLoginAttemptsErrorModal p')), 'your account access has been blocked') !== false) {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure(1);
        } elseif ($this->exts->getElementByText('div.login-container span', ['Error 500'], null, false) != null) {
            $this->exts->account_not_ready();
        } else {
            $this->exts->capture("LoginFailed");
            $this->exts->loginFailure();
        }
    }

    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        try {
            sleep(1);
            if ($this->exts->exists($this->username_selector) && $this->exts->exists($this->password_selector)) {
                sleep(1);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);
                $this->exts->capture("2-login-page-filled");

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(7);

                $error_text = strtolower($this->exts->extract('div.form-group span.text-wrap'));
                $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
                if (stripos($error_text, strtolower('Login error')) !== false) {
                    $this->exts->loginFailure(1);
                }
            } else {
                $this->exts->capture("2-login-page-not-found");
            }

            sleep(10);
        } catch (\Exception $exception) {
            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#code';
        $two_factor_message_selector = '//input[@id="code"]/../../preceding-sibling::p';
        $two_factor_submit_selector = 'div.login-form button[type="submit"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector, null, 'xpath') != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector, null, 'xpath')); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector, null, 'xpath')[$i]->getAttribute('innerText') . "\n";
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


    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            $this->exts->waitTillPresent('button#closeFeaturePopup', 5);

            $this->exts->moveToElementAndClick('button#closeFeaturePopup');
            sleep(4);
            $this->exts->waitTillPresent('a[href*="/logout"], a[href*="/settings"]');
            if (count($this->exts->getElements('a[href*="/logout"], a[href*="/settings"]')) != 0) {
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

        // $this->exts->moveToElementAndClick('ul.navbar-nav a[href*="/settings"]');
        // sleep(5);

        if ($this->exts->exists('ul.sidebar-elements:not(.sidebar-elements__mob) a[href*="/billing"]')) {
            $this->exts->moveToElementAndClick('ul.sidebar-elements:not(.sidebar-elements__mob) a[href*="/billing"]');
            sleep(10);
        } else {
            if ($this->exts->exists('ul a[href*="/billing"]')) {
                $this->exts->moveToElementAndClick('ul a[href*="/billing"]');
                sleep(10);
            }
        }

        $this->exts->moveToElementAndClick('.billing-tabs .billing-tabs-list [data-billingtabs-tab="invoices"] a');
        sleep(10);

        $this->downloadInvoice();
    }

    /**
     *method to download incoice
     */
    public $totalFiles = 0;
    private function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        try {
            if ($this->exts->getElement('div.historyTable table > tbody > tr') != null) {
                $receipts = $this->exts->getElements('div.historyTable table > tbody > tr');
                $invoices = array();
                foreach ($receipts as $i => $receipt) {
                    $tags = $this->exts->getElements('td', $receipt);
                    if (count($tags) >= 5 && $this->exts->getElement('td a[href*="/billing/invoice"]', $receipt) != null) {
                        $receiptDate = trim($tags[0]->getText());
                        $receiptUrl = $this->exts->extract('td:last-child a[href*="/billing/invoice"]', $receipt, 'href');
                        $receiptName = trim($tags[1]->getText());
                        $receiptAmount = trim($tags[2]->getText());
                        $receiptFileName = '';
                        if (trim($receiptName) != '') {
                            $receiptFileName = !empty($receiptName) ? $receiptName . '.pdf' : '';
                        }


                        $this->exts->log("_____________________" . ($i + 1) . "___________________________________________");
                        $this->exts->log("Invoice Date: " . $receiptDate);
                        $this->exts->log("Invoice Name: " . $receiptName);
                        $this->exts->log("Invoice Amount: " . $receiptAmount);
                        $this->exts->log("Invoice Url: " . $receiptUrl);
                        $this->exts->log("Invoice FileName: " . $receiptFileName);
                        $this->exts->log("________________________________________________________________");

                        $invoice = array(
                            'receiptDate' => $receiptDate,
                            'receiptName' => $receiptName,
                            'receiptAmount' => $receiptAmount,
                            'receiptUrl' => $receiptUrl,
                            'receiptFileName' => $receiptFileName
                        );
                        array_push($invoices, $invoice);
                    }
                }

                $this->exts->log(">>>>>>>>>>>>>>>>>>>Invoice found: " . count($invoices));

                $count = 1;
                foreach ($invoices as $invoice) {

                    if ($restrictPages != 0 && $this->totalFiles >= 50) {
                        return;
                    }

                    $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                    $this->exts->log("Download file: " . $downloaded_file);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['receiptDate'], $invoice['receiptAmount'], $downloaded_file);
                        sleep(1);
                        $count++;
                        $this->totalFiles++;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
