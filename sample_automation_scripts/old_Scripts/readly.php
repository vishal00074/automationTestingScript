<?php // migrated scritp and updated download

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

    // Server-Portal-ID: 57869 - Last modified: 19.04.2024 13:42:52 UTC - User: 1

    public $baseUrl = "https://de.readly.com/accounts/subscriptions";
    public $loginUrl = "https://de.readly.com/accounts/login";
    public $username_selector = '#login_form input[name="account[email]"]';
    public $password_selector = '#login_form input[name="account[password]"]';
    public $submit_button_selector = '#login_form [type="submit"]';
    public $login_tryout = 0;
    public $restrictPages = 3;
    public $isNoInvoice = true;


    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->openUrl($this->baseUrl);
        sleep(5);

        if ($this->exts->exists('#cookie-accept-all')) {
            $this->exts->moveToElementAndClick('#cookie-accept-all');
            sleep(1);
        }

        if ($this->exts->exists('div[data-testid="cookies-dialog-accept-all"]>button')) {
            $this->exts->moveToElementAndClick('div[data-testid="cookies-dialog-accept-all"]>button');
            sleep(1);
        }
        $this->exts->capture("Home-page-without-cookie");

        $isCookieLoginSuccess = false;
        if ($this->exts->loadCookiesFromFile()) {
            $this->exts->openUrl($this->baseUrl);
            sleep(10);

            if ($this->checkLogin()) {
                $isCookieLoginSuccess = true;
            } else {
                $this->exts->clearCookies();
                $this->exts->openUrl($this->loginUrl);
                sleep(5);

                if ($this->exts->exists('#cookie-accept-all, div[class*="CookieConsentButtonContainer"]')) {
                    $this->exts->moveToElementAndClick('#cookie-accept-all, div[class*="CookieConsentButtonContainer"]');
                    sleep(1);
                }
            }
        }

        if (!$isCookieLoginSuccess) {
            $this->exts->capture("after-login-clicked");

            $this->fillForm(0);
            sleep(10);
            if ($this->exts->exists('div.alert-danger')) {
                $this->exts->log("Message after login: " . $this->exts->extract('div.alert-danger', null, 'innerText'));
                $this->exts->capture("after-login-submited");
                if ($this->exts->exists('div#cookie-accept-all, div[class*="CookieConsentButtonContainer"]')) {
                    $this->exts->moveToElementAndClick('div#cookie-accept-all, div[class*="CookieConsentButtonContainer"]');
                    sleep(1);
                }
                // For this user the site requires login to another url.
                $mesg = strtolower($this->exts->extract('div.alert-danger', null, 'innerText'));
                if (strpos($mesg, 'you have been redirected to the home page of the account\'s country. please log in again.') !== false || strpos($mesg, 'du wurdest auf die login-seite deines landes weitergeleitet. bitte logge dich erneut ein') !== false) {
                    $this->fillForm(0);
                    sleep(10);
                }
            }


            // if($this->exts->getElement('div.myAccount[style*="display: none;"]') != null) {
            // 	$this->exts->loginFailure();
            // }

            if ($this->checkLogin()) {
                $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
                $this->exts->capture("LoginSuccess");
                $this->invoicePage();
            } else {

                $err_msg1 = $this->exts->extract('form[id="login_form"]  div[class="disclaimer v2 inline-flash-container red"]');
                if ($err_msg1 !== null && strpos(strtolower($err_msg1), 'ungültige anmeldedaten') !== false) {
                    if (strpos(strtolower($err_msg1), 'ungültige anmeldedaten') === 0 || strpos(strtolower($err_msg1), 'ungültige anmeldedaten') > 0) {
                        $this->exts->log($err_msg1);
                        $this->exts->loginFailure(1);
                    }
                }

                if ($this->exts->exists('.disclaimer p') && strpos(strtolower($this->exts->extract('.disclaimer p')), 'passwor') !== false) {
                    $this->exts->log("Login failed!!!! " . $this->exts->extract('.disclaimer p'));
                    $this->exts->loginFailure(1);
                } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
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
            $this->exts->moveToElementAndClick('a.main-header-button');
            sleep(5);
            if ($this->exts->getElement($this->username_selector) != null) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(3);
                $this->exts->capture("1-pre-login-form");
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(3);

                $this->exts->moveToElementAndClick($this->submit_button_selector);
                sleep(5);
            }

            sleep(5);
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
            if ($this->exts->getElement('input[id="account_email"]') != null) {
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
        // $this->exts->openUrl($this->baseUrl);
        if (stripos($this->exts->getUrl(), '/accounts/subscriptions') == false) {
            if ($this->exts->exists('a[href="/accounts/subscriptions"]')) {
                $this->exts->moveToElementAndClick('a[href="/accounts/subscriptions"]');
            } else {
                $this->exts->openUrl($this->baseUrl);
            }
            sleep(10);
        }

        $this->processInvoices();
        sleep(7);

        if ($this->isNoInvoice) {
            $this->exts->log("No invoice !!! ");
            $this->exts->no_invoice();
        }
        $this->exts->success();
    }

    /**
     *method to download incoice
     */

    private function processInvoices()
    {
        sleep(25);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('ul.transactions-list li.list-group-item');
        if (count($rows) == 0) {
            $this->exts->openUrl('https://de.readly.com/accounts/edit');
            sleep(10);

            $this->exts->moveToElementAndClick('a[href*="accounts/subscriptions"]');
            sleep(15);
        }
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('.subscriptions-table tbody tr td', $row);
            if (count($tags) >= 4 && $this->exts->getElement('a[onclick*="Print"]', $row) != null) {
                $this->isNoInvoice = false;
                $invoiceName = trim($this->getInnerTextByJS($tags[0]));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                $invoiceDate = trim($this->getInnerTextByJS($tags[1]));
                $invoiceAmount = $this->exts->extract('tr td:nth-child(3)', $row);

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
                    $download_button = $this->exts->getElement('a[class="action-button-new-secondary green"]', $row);
                    sleep(10);
                    $downloaded_file = $this->exts->click_and_download($download_button, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }

    function getInnerTextByJS($selector_or_object, $parent = null)
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
