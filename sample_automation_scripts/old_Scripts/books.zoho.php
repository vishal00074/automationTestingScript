<?php //  added code to account_not_ready and loginfailedConfirmed in case wrong added check to not call 2fa in case login form screen

/**
 * Chrome Remote via Chrome devtool protocol script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/GmiChromeManager.php');
require_once($gmi_selenium_core);

class PortalScriptCDP
{

    private    $exts;
    public    $setupSuccess = false;
    private $chrome_manage;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiChromeManager();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673461/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        if (!empty($this->exts->config_array['portal_domain'])) {
            $this->portal_domain = $this->exts->config_array['portal_domain'];
        }
    }

    /**
     * Method that called first for executing portal script, this method should not be altered by Users.
     */
    public function run()
    {
        if ($this->setupSuccess) {
            file_put_contents($this->exts->screen_capture_location . '.script_execution_started', time());
            try {
                // Start portal script execution
                $this->initPortal(0);

                $this->exts->dump_session_files();
            } catch (\Exception $exception) {
                $this->exts->log('Selenium Exception: ' . $exception->getMessage());
                $this->exts->capture("error");
                var_dump($exception);
            }

            $this->exts->log('Execution completed');

            $this->exts->process_completed();
        } else {
            echo 'Script execution failed.. ' . "\n";
        }
    }

    // Server-Portal-ID: 1287709 - Last modified: 23.08.2025 19:21:54 UTC - User: 1

    public $baseUrl = 'https://accounts.zoho.com/signin';
    public $loginUrl = 'https://accounts.zoho.com/signin';
    public $poenurl = 'https://books.zoho.eu/app/';
    public $username_selector = 'input#login_id';
    public $password_selector = 'input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#nextbtn';
    public $check_login_failed_selector = 'div.errorlabel';
    public $check_login_success_selector = 'div[id="profile-section"]';
    public $isNoInvoice = true;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->loadCookiesFromFile();
        $this->exts->openUrl($this->loginUrl);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            if ($this->exts->exists("button[onclick=\"toggleCustomPopup('.exit-popup-container', true);\"]")) {
                $this->exts->click_element("button[onclick=\"toggleCustomPopup('.exit-popup-container', true);\"]");
            } else {
                $this->exts->log("Not Found Pop-Up !");
            }
            $this->fillForm(0);
            sleep(2);
            if ($this->exts->exists('div > a[class="remindlaterdomains"]')) {
                $this->exts->click_element('div > a[class="remindlaterdomains"]');
            }
            sleep(10);
            if ($this->exts->exists('div#mfa_email') && $this->exts->queryXpath(".//div[@id='enablemore' and @style='display: block;']//span[normalize-space(text())='Sign in using email OTP']") == null)  {
                $this->checkFillTwoFactor();
            }
            sleep(5);
            if ($this->exts->exists('form[name="login"] span[id="headtitle"]') && $this->exts->queryXpath(".//div[@id='enablemore' and @style='display: block;']//span[normalize-space(text())='Sign in using email OTP']") == null) {
                $this->checkFill2FAPushNotification();
            }
            sleep(5);
            $this->waitFor('button.trustbtn');
            if ($this->exts->exists('button.trustbtn')) {
                $this->exts->click_element('button.trustbtn');
            }
            sleep(5);
            $this->exts->openUrl($this->poenurl);
            sleep(10);
            $this->waitFor('#profile-section img[alt="Gerrit Jessen"]', 5);
            if ($this->exts->exists('#profile-section img[alt="Gerrit Jessen"]')) {
                $this->exts->click_element('#profile-section img[alt="Gerrit Jessen"]');
            }
        }

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->exts->success();
            sleep(4);
            $this->exts->openUrl("https://books.zoho.eu/app/#/invoices?");
            sleep(10);
            $this->processInvoices();
            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwor') !== false) {
                $this->exts->log("Wrong credential !!!!");
                $this->exts->loginFailure(1);
            } else if ($this->exts->querySelector('div.books-initialsetup') != null && $this->exts->queryXpath(".//label[normalize-space(text())='Organization Name']") != null) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function waitFor($selector, $seconds = 7)
    {
        for ($wait = 0; $wait < 2 && $this->exts->executeSafeScript("return !!document.querySelector('" . $selector . "');") != 1; $wait++) {
            $this->exts->log('Waiting for Selectors.....');
            sleep($seconds);
        }
    }
    private function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);
        $this->waitFor($this->username_selector);
        try {
            if ($this->exts->querySelector($this->username_selector) != null) {

                $this->exts->capture("1-pre-login");
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);

                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(10);
                }

                $error_text = strtolower($this->exts->extract('div[class="fielderror errorlabel"]'));
                $this->exts->log("Error text:: " . $error_text);

                if (stripos($error_text, strtolower('account cannot be found')) !== false) {
                    $this->exts->loginFailure(1);
                }

                sleep(2);
                $this->exts->type_key_by_xdotool('Return');
                sleep(5);
                if ($this->exts->exists('form[name="login"] span[id="headtitle"]') && $this->exts->queryXpath(".//div[@id='enablemore' and @style='display: block;']//span[normalize-space(text())='Sign in using email OTP']") == null) {
                    $this->checkFill2FAPushNotification();
                }

                $this->exts->log("Enter Password");
                $this->waitFor($this->password_selector);
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(2);

                $this->exts->capture("1-login-page-filled");
                sleep(5);
                if ($this->exts->exists($this->submit_login_selector)) {
                    $this->exts->click_by_xdotool($this->submit_login_selector);
                    sleep(7);
                }

                $error_text_pass = strtolower($this->exts->extract('div[class="fielderror errorlabel"]'));
                $this->exts->log("Error text pass:: " . $error_text_pass);
                if (stripos($error_text_pass, strtolower('passwor')) !== false) {
                    $this->exts->loginFailure(1);
                }
            }
        } catch (\Exception $exception) {

            $this->exts->log("Exception filling loginform " . $exception->getMessage());
        }
    }

    private function checkFill2FAPushNotification()
    {
        sleep(5);
        $two_factor_message_selector = 'form[name="login"] span[id="headtitle"]';
        $two_factor_submit_selector = '';
        $this->waitFor($two_factor_message_selector, 10);
        if (stripos(strtolower($this->exts->extract($two_factor_message_selector)), 'push notification') !== false && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->querySelector($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
                $this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en) . ' Please input "OK" when finished!!';
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
                $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            }
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $two_factor_code = trim(strtolower($this->exts->fetchTwoFactorCode()));
            if (!empty($two_factor_code) && trim($two_factor_code) == 'ok') {
                $this->exts->log("checkFillTwoFactorForMobileAcc: Entering two_factor_code." . $two_factor_code);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);
                sleep(15);
                if ($this->exts->querySelector($two_factor_message_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->two_factor_attempts++;
                    $this->exts->notification_uid = '';
                    $this->checkFill2FAPushNotification();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = '#mfa_email input , div#mfa_totp input.mfa_totp_otp ,input[id="verifycaptcha"]';
        $two_factor_message_selector = 'div#signin_div div.service_name , span[id="backup_title"] span:nth-child(2)';
        $two_factor_submit_selector = 'button#nextbtn , form[id="verifycaptcha_container"] >button';
        $this->waitFor($two_factor_selector, 10);
        if ($this->exts->querySelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");
            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = $this->exts->extract($two_factor_message_selector);
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
                $this->exts->click_by_xdotool($two_factor_selector);
                sleep(2);
                $this->exts->type_text_by_xdotool($two_factor_code);


                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->click_by_xdotool($two_factor_submit_selector);
                sleep(15);
                if ($this->exts->querySelector($two_factor_selector) == null) {
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


    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */

    private function checkLogin()
    {
        $this->exts->log("Begin checkLogin ");
        $isLoggedIn = false;
        try {
            sleep(20);
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

    private function check_box_click()
    {
        $this->exts->execute_javascript("
        const inputs = document.querySelectorAll('table tbody tr td:nth-child(1) input');
        inputs.forEach((input, index) => {
            if (index < 100 && !input.checked) {
                input.click(); // Toggle checkbox/radio
            }
        });
    ");
    }

    private function processInvoices($paging_count = 1)
    {
        $this->waitFor('table tbody tr', 15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->querySelectorAll('table tbody tr');
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)$this->exts->config_array["restrictPages"] : 3;
        $restrictDate = $restrictPages == 0 ? date('Y-m-d', strtotime('-2 years')) : date('Y-m-d', strtotime('-3 months'));
        $dateRestriction = true;

        $maxInvoices = 5;
        $invoiceCount = 0;

        $invoices = [];

        foreach ($rows as $row) {
            $this->isNoInvoice = false;

            $invoiceDate = $row->querySelector('td:nth-child(2)');
            if ($invoiceDate != null) {
                $invoiceDateText = $invoiceDate->getText();
                $invoiceDate = $this->exts->parse_date($invoiceDateText, '', 'Y-m-d');
            }

            $amount = $row->querySelector('td:nth-child(8)');
            if ($amount != null) {
                $amount = $amount->getText();
            }

            $detailsBtn = $row->querySelector('td:nth-child(3) > a');

            if ($detailsBtn != null) {
                array_push($invoices, array(
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $amount,
                    'invoiceUrl' => $detailsBtn->getAttribute('href')
                ));
            }
        }
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(15);
            $this->waitFor('ul.details-menu-bar > li.nav-item > button', 10);
            $detailsItems = $this->exts->querySelector('ul.details-menu-bar > li.nav-item > button');

            if ($detailsItems != null) {
                $this->exts->execute_javascript("arguments[0].click();", [$detailsItems]);
                sleep(3);

                $this->waitFor('div.finance-app > div[id="overlay-wrapper"] > div.dropdown-menu > button:first-child', 10);
                $downloadBtn = $this->exts->querySelector('div.finance-app > div[id="overlay-wrapper"] > div.dropdown-menu > button:first-child');

                if ($downloadBtn != null) {
                    $this->exts->execute_javascript("arguments[0].click();", [$downloadBtn]);
                    sleep(2);

                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf');
                    $invoiceFileName = basename($downloaded_file);

                    $invoiceName = substr($invoiceFileName, 0, strrpos($invoiceFileName, '.'));

                    $this->exts->log('invoiceName: ' . $invoiceName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                        sleep(1);
                        $invoiceCount++;
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }

            $lastDate = !empty($invoice['invoiceDate']) && $invoice['invoiceDate'] <= $restrictDate;

            if ($restrictPages != 0 && ($invoiceCount == $maxInvoices || ($dateRestriction && $lastDate))) {
                break;
            } else if ($restrictPages == 0 && $dateRestriction && $lastDate) {
                break;
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
