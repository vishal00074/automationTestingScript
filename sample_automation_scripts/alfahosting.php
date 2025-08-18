<?php // migrated and handle empty invoice name

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
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673482/screens/';
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
                // load cookies from file for desktop app
                if (!empty($this->exts->config_array["without_password"])) {
                    $this->exts->loadCookiesFromFile();
                }
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

    // Server-Portal-ID: 458 - Last modified: 20.03.2025 07:51:13 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://secure.alfahosting.de/kunden/index.php/Kundencenter:index';
    public $loginUrl = 'https://alfahosting.de/kunden-login/';
    public $invoicePageUrl = 'https://secure.alfahosting.de/kunden/index.php/Kundencenter:Rechnung';

    public $username_selector = 'form#loginForm input#username';
    public $password_selector = 'form#loginForm input#password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'form#loginForm input[type="submit"]';

    public $check_login_failed_selector = 'div#errorSection';
    public $check_login_success_selector = 'li.logout';

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

        $this->exts->moveToElementAndClick('div.cookiefirst-root button[data-cookiefirst-button="primary"]');
        sleep(5);

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            // if login page do the login once before clearing the cookie
            if ($this->exts->exists('a.cmptxt_btn_yes')) {
                $this->exts->moveToElementAndClick('a.cmptxt_btn_yes');
                sleep(5);
            }
            $this->checkFillLogin();
            sleep(10);

            $this->processTwoFactor();
            sleep(15);

            // if($this->exts->getElement($this->check_login_success_selector) == null) {
            // 	$this->exts->init_required();
            // }
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->openUrl($this->invoicePageUrl);
            sleep(5);

            if ($this->exts->getElement($this->check_login_success_selector) == null) {
                $this->checkFillLogin();
                sleep(5);

                $this->processTwoFactor();
            }

            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    function processTwoFactor()
    {
        $this->exts->log(__FUNCTION__ . '::Checkk Two Factor');
        $this->exts->notification_uid = "";
        if ($this->exts->exists('form#loginOtpForm input#otp') &&  $this->exts->exists('form#loginOtpForm button[type="submit"]')) {
            $this->checkFillTwoFactor('form#loginOtpForm input#otp', 'div#loginOtp p', 'form#loginOtpForm button[type="submit"]');
        } else if ($this->exts->exists('form#loginOtpForm input#otp') && $this->exts->exists('form#loginOtpForm input[type="submit"]')) {
            $this->checkFillTwoFactor('form#loginOtpForm input#otp', 'div#loginOtp p', 'form#loginOtpForm input[type="submit"]');
        } else if ($this->exts->exists('form#loginProtectionForm input#code')) {
            if ($this->exts->exists('form#loginProtectionForm input[type="submit"]')) {
                $this->checkFillTwoFactor('form#loginProtectionForm input#code', 'div#loginProtection h3 ~ p', 'form#loginProtectionForm input[type="submit"]');
            } else if ($this->exts->exists('form#loginProtectionForm button[type="submit"]')) {
                $this->checkFillTwoFactor('form#loginProtectionForm input#code', 'div#loginProtection h3 ~ p', 'form#loginProtectionForm button[type="submit"]');
            } else {
                $this->checkFillTwoFactor('form#loginProtectionForm input#code', 'div#loginProtection h3 ~ p', 'form#loginProtectionForm [type="submit"]');
            }
        }
    }

    private function checkFillTwoFactor($two_factor_selector, $two_factor_message_selector, $two_factor_submit_selector)
    {
        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->getInnerTextByJS($this->exts->getElements($two_factor_message_selector)[$i]) . "\n";
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

    private function processInvoices($pageCount = 0)
    {
        sleep(15);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $paths = explode('/', $this->exts->getUrl());
        $currentDomainUrl = $paths[0] . '//' . $paths[2];
        $rows = $this->exts->getElements('ul#invoice-list>li');
        foreach ($rows as $index => $row) {
            if ($this->exts->getElement('dd a[href*="/invoice.php?id="]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('dd a[href*="/invoice.php?id="]', $row)->getAttribute('href');
                if (strpos($invoiceUrl, $currentDomainUrl) === false && strpos($invoiceUrl, 'http') === false) {
                    $invoiceUrl = $currentDomainUrl . $invoiceUrl;
                }
                $invoiceName = end(explode('?id=', $invoiceUrl));
                $invoiceDate = trim($this->getInnerTextByJS('dd.date', $row));
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = end(explode(',', $invoiceDate));
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = str_replace('.', '', $invoiceDate);
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate);
                $this->exts->log('Date after parsed: ' . $invoiceDate);
                $invoiceAmount = '';

                $this->isNoInvoice = false;
                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $this->exts->log('invoiceURL: ' . $invoiceUrl);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf': '';
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // click and download invoice
                    $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }
        }

        // next page
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0 && $pageCount < 50 && $this->exts->getElement('div#paginator a[href*="page"]') != null) {
            $count = count($this->exts->getElements('div#paginator a[href*="page"]'));
            $nextPageSel = 'div#paginator a[href*="page"]:nth-child("' . ($count - 1) . '")';
            if ($this->exts->getElement($nextPageSel) != null) {
                $pageCount++;
                $this->exts->log('page number ' . $pageCount);
                $this->exts->moveToElementAndClick($nextPageSel);
                sleep(5);
                $this->processInvoices($pageCount);
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'Immowelt Kundenportal', '2673482', 'aW5mb0B0b20taW1tb2JpbGllbi5jb20=', 'UXQlb3FrV2VAKExSYjI=');
$portal->run();
