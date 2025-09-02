<?php // added else block in case success not found then stop execution with loginFailure()

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

    // Server-Portal-ID: 5433 - Last modified: 28.08.2025 15:00:44 UTC - User: 1

    public $baseUrl = 'https://mein.stadtmobil.de/';
    public $loginUrl = 'https://mein.stadtmobil.de/';
    public $invoicePageUrl = 'https://mein.stadtmobil.de/';
    public $username_selector = 'input#login_username';
    public $password_selector = 'input#login_password';
    public $remember_me_selector = 'input#cbx_storeLogin';
    public $submit_login_selector = 'button.login__submit';
    public $check_login_failed_selector = 'input#login_password.field__error';
    public $check_login_success_selector = "li[class*='logout']:not([style*='display: none'])";
    public $isNoInvoice = true;
    public $restrictPages = 3;
    public $totalInvoices = 0;
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
        $this->waitForSelectors($this->check_login_success_selector, 20, 2);
        if ($this->exts->querySelector($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->waitForSelectors("button.menu__button--login", 10, 2);
            if ($this->exts->querySelector('button.menu__button--login') != null) {
                $this->exts->moveToElementAndClick('button.menu__button--login');
                sleep(2);
            }
            $this->checkFillLogin();
        }

        // then check user logged in or not
        if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->loginFailure(1);
        }
        sleep(20);
        $this->waitForSelectors($this->check_login_success_selector, 20, 2);
        if ($this->exts->querySelector($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            // Open invoices url and download invoice
            $this->exts->moveToElementAndClick('button.menu__button--account');
            sleep(2);
            $this->exts->moveToElementAndClick('button.tabmenu__button--bills');
            sleep(2);

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

            if ($restrictPages === 0) {
                for ($i = 0; $i < 2; $i++) {

                    $selectVal = (int)date('Y') - $i . "-01";
                    $this->exts->log("restrictPages:-- " . $selectVal);
                    $this->changeSelectbox('select#bills__option--timeframe', $selectVal);
                    $this->processInvoices();
                }
            } else {
                $this->exts->log("processInvoices");
                $this->processInvoices();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $this->exts->loginFailure();
        }
    }

    private function changeSelectbox($select_box = '', $option_value = '')
    {
        $this->exts->waitTillPresent($select_box, 10);
        if ($this->exts->exists($select_box)) {
            $option = $select_box . ' option[value="' . $option_value . '"]';
            $this->exts->log('Option Box : ' . $option);
            $this->exts->click_element($select_box);
            sleep(1);
            if ($this->exts->exists($option)) {
                $this->exts->log('Select box Option exists');
                try {
                    $this->exts->execute_javascript(
                        'var select = document.querySelector("' . $select_box . '"); 
                    if (select) {
                        select.value = "' . $option_value . '";
                        select.dispatchEvent(new Event("change", { bubbles: true }));
                    }'
                    );
                } catch (\Exception $e) {
                    $this->exts->log('JavaScript selection failed, error: ' . $e->getMessage());
                }

                sleep(3);
            } else {
                $this->exts->log('Select box Option does not exist');
            }
        } else {
            $this->exts->log('Select box does not exist');
        }
    }


    private function checkFillLogin()
    {
        $this->waitForSelectors($this->password_selector, 10, 2);
        if ($this->exts->querySelector($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);
            $client_region = isset($this->exts->config_array["client_region"]) ? (int)@$this->exts->config_array["client_region"] : 88;
            if ($this->exts->querySelector('select.login__select') != null) {
                $this->changeSelectbox('select.login__select', $client_region, 2);
            }
            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);

            $this->exts->waitTillPresent($this->check_login_failed_selector, 10);
            if ($this->exts->exists($this->check_login_failed_selector)) {
                $this->exts->loginFailure(1);
            }

            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            }
            if ($this->exts->querySelector($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    public function hasAlphabetInInvoiceNumber($invoiceNumber)
    {
        // Use a regular expression to check for alphabets
        return preg_match('/[a-zA-Z]/', $invoiceNumber) === 1;
    }


    private function waitForSelectors($selector, $max_attempt, $sec)
    {
        for (
            $wait = 0;
            $wait < $max_attempt && $this->exts->executeSafeScript("return !!document.querySelector(\"" . $selector . "\");") != 1;
            $wait++
        ) {
            $this->exts->log('Waiting for Selectors!!!!!!');
            sleep($sec);
        }
    }

    private function processInvoices()
    {
        $this->exts->waitTillPresent('tr.bills__item', 20);
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $onlyYears = isset($this->exts->config_array["only_years"]) ? (int)@$this->exts->config_array["only_years"] : 0;
        $rows_len = count($this->exts->querySelectorAll('tr.bills__item'));
        for ($i = 0; $i < $rows_len; $i++) {
            if ($this->restrictPages != 0 && $this->totalInvoices >= 10) {
                return;
            };
            $row = $this->exts->querySelectorAll('tr.bills__item')[$i];
            $tags = $this->exts->querySelectorAll('td', $row);
            if ($this->exts->querySelectorAll(' button.bills__download--pdf', $row) != null) {
                $download_button = $this->exts->querySelector(' button.bills__download--pdf', $row);
                $invoiceName = str_replace('-', '', trim($tags[1]->getAttribute('innerText')));
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[2]->getAttribute('innerText'))) . ' EUR';

                $this->isNoInvoice = false;

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);


                if ($onlyYears  &&  $this->hasAlphabetInInvoiceNumber($invoiceName)) {
                    $this->exts->log('onlyYear: ' . $onlyYears . "---" . $invoiceName);

                    if ($this->exts->invoice_exists($invoiceFileName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {

                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);

                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);

                            sleep(1);
                            $this->totalInvoices++;
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                } else if ($onlyYears === 0) {
                    $this->exts->log('onlyYear: ' . $onlyYears);


                    if ($this->exts->invoice_exists($invoiceFileName) || $this->exts->document_exists($invoiceFileName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {

                        try {
                            $this->exts->log('Click download button');
                            $download_button->click();
                        } catch (\Exception $exception) {
                            $this->exts->log('Click download button by javascript');
                            $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                        }
                        sleep(5);

                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                            sleep(1);
                            $this->totalInvoices++;
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }
                    }
                }
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'stadtmobil.de - carsharing', '2673553', 'aW5mbzIuMEBrdXJhc2FuLWthcmxzcnVoZS5kZQ==', 'UGh5c2lvIEtB');
$portal->run();
