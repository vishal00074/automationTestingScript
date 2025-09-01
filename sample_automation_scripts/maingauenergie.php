<?php // uncomment loadCookiesFromFile migrated

/**
 * Selenium script, for specific process/portal
 *
 * @package	uwa
 *
 * @copyright	GetMyInvoices
 */

namespace Facebook\WebDriver;

define('KERNEL_ROOT', '/var/www/vhosts/worker/httpdocs/src/');

$gmi_selenium_core = realpath(KERNEL_ROOT . 'modules/cust_gmi_worker/includes/selenium.php');
require_once($gmi_selenium_core);

class PortalScript
{

    private    $exts;
    public    $setupSuccess = false;
    private    $username;
    private    $password;
    public $support_restart = true;
    public $portal_domain = '';
    private static $myexts;

    public function __construct($mode, $portal_name, $process_uid, $username, $password)
    {

        $this->username = base64_decode($username);
        $this->password = base64_decode($password);

        $this->exts = new GmiSelenium();
        $this->exts->screen_capture_location = '/var/www/vhosts/worker/httpdocs/fs/cdo/process/2/2673370/screens/';
        $this->exts->init($mode, $portal_name, $process_uid, $this->username, $this->password);
        $this->setupSuccess = true;
        $myexts = $this->exts;
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
            try {
                // Start portal script execution
                $this->initPortal(0);

                // Save updated cookies
                $this->exts->dumpCookies();

                // Save updated localStorage
                $this->exts->dumpLocalStorage();

                // Save updated sessionStorage
                $this->exts->dumpSessionStorage();
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

    /**
     * Navigate url Catch exception, if failed then restart docker and process
     *
     * @param	string	$url
     * @return	void
     */
    public function openUrl($url)
    {
        $this->exts->openUrl($url);

        // Check if docker restart required
        if ($this->support_restart && $this->exts->docker_need_restart) {
            $this->exts->restart();

            // If docker restarted failed, it will die and below code will not get executed
            $this->initPortal(0);
        }
    }

    /**********************************************************************/
    /**************Portal Specific Script Should Begin Now*****************/
    /**********************************************************************/

    // Server-Portal-ID: 28835 - Last modified: 30.04.2024 14:32:17 UTC - User: 1
    public $baseUrl = 'https://onlineservice.service-rz.de/?act=dashboard&werknr=52';
    public $loginUrl = 'https://onlineservice.service-rz.de/?act=login&werknr=52';
    public $invoicePageUrl = '';

    public $username_selector = 'input#name1';
    public $email_selector = 'input[name="login[0][email]"], input[id="login_0_email"]';
    public $password_selector = 'form input[name="login[0][pass_asked]"]';
    public $email_password_selector = 'form input[name="login[0][emailPassword]"]';
    public $number_selector = 'input#pin1';
    public $remember_me_selector = '';
    public $submit_login_selector = 'button#submitNormal';
    public $submit_email_login_selector = 'button[onclick*="submitEmail"]';

    public $check_login_success_selector = 'a[href*="logout"]';

    public $isNoInvoice = true;
    public $customer_number = '';
    public $download_all_documents = 0;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        //account_number
        $this->customer_number = isset($this->exts->config_array["account_number"]) ? trim($this->exts->config_array["account_number"]) : $this->customer_number;
        $this->download_all_documents = isset($this->exts->config_array["download_all_documents"]) ? (int)@$this->exts->config_array["download_all_documents"] : $this->download_all_documents;

        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        // sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(20);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            //$this->exts->openUrl($this->exts->getElement('a[href*="dokumente&"]')->getAttribute('href'));
            $this->exts->moveToElementAndClick('a[href*="dokumente&"]');
            $this->processInvoices();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            if ($this->exts->exists(".modal.show p")) {
                $err_msg1 = $this->exts->extract('.modal.show p');
                if ($err_msg1 !== null && strpos(strtolower($err_msg1), 'ihnen angegebenen daten') !== false) {
                    if (strpos(strtolower($err_msg1), 'ihnen angegebenen daten') === 0 || strpos(strtolower($err_msg1), 'ihnen angegebenen daten') > 0) {
                        $this->exts->log($err_msg1);
                        $this->exts->loginFailure(1);
                    }
                }
            }
            sleep(5);
            if (strpos(strtolower($this->exts->extract('.login_form .modal.show #serviceError, .modal.show p')), 'passwort ist falsch!') !== false) {
                $this->exts->loginFailure(1);
            } else if (stripos($this->exts->extract('.login_form .modal.show #serviceError, .modal.show p'), 'Leider steht dieser Service momentan') !== false) {
                $this->exts->account_not_ready();
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if (filter_var($this->username, FILTER_VALIDATE_EMAIL) !== false) {
            $this->exts->moveToElementAndClick('section#email a[href*="email"]');
            sleep(5);

            if ($this->exts->getElement($this->email_password_selector) != null) {
                sleep(3);
                $this->exts->capture("2-login-page");

                $this->exts->log("Enter Email Address");

                $this->exts->moveToElementAndType($this->email_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->email_password_selector, $this->password);
                sleep(1);

                if ($this->remember_me_selector != '')
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(2);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_email_login_selector);
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-login-page-not-found");
            }
        } else {
            if ($this->exts->getElement($this->number_selector) != null && $this->customer_number != "") {
                sleep(3);
                $this->exts->capture("2-login-page");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);

                $this->exts->log("Enter Customer Number");
                $this->exts->moveToElementAndType($this->number_selector, $this->customer_number);
                sleep(1);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(2);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                if ($this->remember_me_selector != '')
                    $this->exts->moveToElementAndClick($this->remember_me_selector);
                sleep(2);

                $this->exts->capture("2.1-login-page-filled");
                $btns = $this->exts->getElements($this->submit_login_selector);
                if (count($btns) > 1) {
                    try {
                        $btns[count($btns) - 1]->click();
                    } catch (\Exception $exception) {
                        $this->exts->executeSafeScript('arguments[0].click();', [$btns[count($btns) - 1]]);
                    }
                } else {
                    $this->exts->moveToElementAndClick($this->submit_login_selector);
                }
            } else if ($this->customer_number == "") {
                $this->exts->log("customer_number null");
                $this->exts->loginFailure(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::Login page not found');
                $this->exts->capture("2-login-page-not-found");
            }
        }
    }

    private function processInvoices()
    {
        sleep(15);

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div.archiv-list-row');
        foreach ($rows as $index => $row) {
            $tags = $this->exts->getElements('div.archiv-list-column', $row);
            if (count($tags) >= 3 && $this->exts->getElement('button', $tags[0]) != null) {
                $invoiceSelector = $this->exts->getElement('button', $tags[0]);
                $this->exts->webdriver->executeScript("arguments[0].setAttribute('data-id', '" . $index . "');", [$invoiceSelector]);
                $invoiceName = trim($tags[0]->getText());
                $invoiceDate = trim($tags[2]->getText());
                $invoiceAmount = '';

                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->isNoInvoice = false;
                $invoiceFileName =  !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    // click and download invoice
                    $this->exts->moveToElementAndClick('button[data-id="' . $index . '"]');
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }
        }
    }
}

$portal = new PortalScript("chrome", 'MAINGAU Energie', '2673370', 'S2F1cw==', 'TUstSW1tb18wODE1Pw==');
$portal->run();
