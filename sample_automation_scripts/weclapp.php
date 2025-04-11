<?php // migrated script and  added conditionin invoice name is empty and optimized script performance
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

    public $baseUrl = 'https://www.weclapp.com/en/login/';
    public $username_selector = 'form#loginform input#user_login';
    public $password_selector = 'form#loginform input#user_pass';
    public $remember_me_selector = 'form#loginform label[for="user_keep_login"]';
    public $submit_login_selector = 'form#loginform button[type="submit"]';

    public $check_login_failed_selector = '.login-outer-block .error-panel span, .login-block .error-panel span';
    public $check_login_success_selector = '#headerMyMenu a[id="naviForm:globalLogoutAction"], a[href*="home"], form [id*="selectAccountPanel"]';

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
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(4);
            $this->checkFillLogin();
            sleep(5);
            $this->checkFillTwoFactor();
        }
        if ($this->checkLogin()) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            if ($this->exts->exists('form [id*="selectAccountPanel"]')) {
                $account_urls = $this->exts->getElementsAttribute('form [id*="selectAccountPanel"] a', 'href');
                foreach ($account_urls as $key => $account_url) {
                    $this->exts->openUrl($account_url);
                    sleep(4);
                    $this->exts->waitTillPresent('li[rel="ic-myaccount"] a[href*="invoices.page"]', 10);

                    $this->exts->moveToElementAndClick('li[rel="ic-myaccount"] a[href*="invoices.page"]');

                    $this->processInvoices();
                }
            } else {
                $paths = explode('/', $this->exts->getUrl());
                $currentDomainUrl = $paths[0] . '//' . $paths[2];
                $invoicePageUrl = $currentDomainUrl . '/webapp/view/account/invoices.page';
                $this->exts->openUrl($invoicePageUrl);
                $this->processInvoices();
            }


            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            if ($this->exts->exists('div[class="error-panel"][style="display: block;"]')) {

                $err_msg1 = $this->exts->extract('div[class="error-panel"][style="display: block;"]');
                $lowercase_err_msg = strtolower($err_msg1);
                $substrings = array('e-mail or password wrong', 'wrong', 'e-mail or password');
                foreach ($substrings as $substring) {
                    if (strpos($lowercase_err_msg, strtolower($substring)) !== false) {
                        $this->exts->log($err_msg1);
                        $this->exts->loginFailure(1);
                        break;
                    }
                }
            }

            if ($this->exts->getElement($this->check_login_failed_selector) != null && strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
                $this->exts->loginFailure(1);
            } else if ($this->exts->getElement($this->check_login_failed_selector) != null && strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'bitte anmeldedaten') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
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
    public function checkLogin()
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

    private function checkFillTwoFactor()
    {

        $two_factor_selector = 'form#loginForm input[name="loginForm:softKey"]';
        $two_factor_message_selector = 'form#loginForm span[id="loginForm:softTokenRequired"]';
        $two_factor_submit_selector = 'form#loginForm input[name="loginForm:loginButton"]';
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
                sleep(5);

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

    private function processInvoices()
    {
        sleep(5);
        $this->exts->waitTillPresent('[id="pageForm:myInvoicesTableTable:tb"] tr');
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = count($this->exts->getElements('[id="pageForm:myInvoicesTableTable:tb"] tr'));
        for ($i = 0; $i < $rows; $i++) {
            // $paths = explode('/', $this->exts->getUrl());
            // $currentDomainUrl = $paths[0].'//'.$paths[2];
            // $invoicePageUrl = $currentDomainUrl . '/webapp/view/account/invoices.page';
            // $this->exts->openUrl($invoicePageUrl);
            $row = $this->exts->getElements('[id="pageForm:myInvoicesTableTable:tb"] tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a.ft-pdf') != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('a.ft-pdf', $row);
                $invoiceName = trim($this->getInnerTextByJS($tags[1]));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceDate = trim(explode(' -', $this->getInnerTextByJS($tags[2]))[0]);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->getInnerTextByJS($tags[4]))) . ' EUR';
                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceFileName: ' . $invoiceFileName);
                $parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $parsed_date);

                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                    }
                    sleep(2);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                    // $this->exts->executeSafeScript("javascript: setTimeout(\"history.go(-1)\", 2000)");
                    // sleep(10);
                }
            }
        }
    }

    public function getInnerTextByJS($selector_or_object, $parent = null)
    {
        if ($selector_or_object == null) {
            $this->exts->log(__FUNCTION__ . ' Can not get innerText of null');
            return;
        }
        $element = $selector_or_object;
        if (is_string($selector_or_object)) {
            $element = $this->exts->getElement($selector_or_object, $parent);
            if ($element == null) {
                $element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
            }
            if ($element == null) {
                $this->exts->log(__FUNCTION__ . ':: Can not found element with selector/xpath: ' . $selector_or_object);
            }
        }
        if ($element != null) {
            return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
