<?php // migrated uncomment loadcookiesfromfile added custom switchToFrame

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

    // Server-Portal-ID: 169046 - Last modified: 20.08.2024 09:22:33 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://agenda-unternehmens-portal.de/Unternehmensportal';

    public $username_selector = 'form#kc-form-login input#username';
    public $password_selector = 'form#kc-form-login input#password';
    public $submit_login_selector = 'form#kc-form-login button#kc-login';

    public $check_login_failed_selector = 'div.alert-error span.kc-feedback-text, .form-error__error-message';
    public $check_login_success_selector = 'button [name="cogwheel"]';

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->exts->openUrl($this->baseUrl);
        sleep(7);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        // sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            // $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->checkFillLogin();
            sleep(10);
            $this->checkFillTwoFactor();
            sleep(10);
        }
        if ($this->exts->urlContains('/mandator-activation')) {
            $this->exts->openUrl($this->baseUrl);
            sleep(7);
        }

        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->process_mandator();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());
            if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector)), 'passwort') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }
    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->log($this->username);
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->log($this->password);
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input[class="digit-input__digit"]';
        $two_factor_message_selector = 'p[class="login-otp__text"]';
        $two_factor_submit_selector = 'button[id="kc-login"]';

        if ($this->exts->getElement($two_factor_selector) != null && $this->exts->two_factor_attempts < 3) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            if ($this->exts->getElement($two_factor_message_selector) != null) {
                $this->exts->two_factor_notif_msg_en = "";
                for ($i = 0; $i < count($this->exts->getElements($two_factor_message_selector)); $i++) {
                    $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . $this->exts->getElements($two_factor_message_selector)[$i]->getAttribute('placeholder') . "\n";
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
                sleep(1);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = "";
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

    private function process_mandator()
    {
        $this->exts->click_element('#mandator');
        sleep(3);
        $this->exts->moveToElementAndType('input[aria-controls="mandator_list"]', ',');
        sleep(3);
        $this->exts->capture('mandator-checking');
        $mandator_names = $this->exts->getElementsAttribute('#mandator li.p-autocomplete-item', 'innerText');
        if (count($mandator_names) > 0) {

            $this->exts->log("mandator_names count  :  " . count($mandator_names));

            foreach ($mandator_names as $mandator_name) {
                $this->exts->log('SELECTING mandator: ' . $mandator_name);
                if (!$this->exts->urlContains('/mandator-selection')) {
                    $this->exts->openUrl('https://agenda-unternehmens-portal.de/Unternehmensportal/mandator-selection');
                    sleep(7);
                    $this->exts->click_element('#mandator');
                    sleep(3);
                    $this->exts->moveToElementAndType('input[aria-controls="mandator_list"]', ',');
                    sleep(3);
                }

                $selected_mandator = $this->exts->getElement('//*[@id="mandator"]//li//*[text()="' . $mandator_name . '"]/..', null, 'xpath');
                if ($selected_mandator == null) {
                    $mandator_name_b_text = explode(',', $mandator_name)[0];
                    $mandator_name = str_replace($mandator_name_b_text . ',', '', $mandator_name);
                    $this->exts->log('SELECTING mandator: ' . $mandator_name);
                    $selected_mandator = $this->exts->getElement('//*[@id="mandator"]//li//*[text()="' . $mandator_name . '"]/..', null, 'xpath');
                }
                $this->exts->click_element($selected_mandator);
                sleep(2);
                $this->exts->moveToElementAndClick('unp-mandator-selection input[type="submit"]:not([disabled])');
                sleep(5);

                // find invoices at Auswertung --> Personlawesen
                if ($this->exts->getElement('//unp-product-selection//unp-product-tile//h1[text()="Auswertungen Online"]', null, 'xpath') != null) {
                    $this->exts->click_element('//unp-product-selection//unp-product-tile//h1[text()="Auswertungen Online"]');
                    sleep(5);
                }


                $this->processInvoices();
                $this->exts->update_process_lock();
            }
        } else {
            if ($this->exts->exists('unp-mandator-selection input[type="submit"]:not([disabled])')) {
                $this->exts->moveToElementAndClick('unp-mandator-selection input[type="submit"]:not([disabled])');
                sleep(5);
            }

            // find invoices at Auswertung --> Personlawesen
            if ($this->exts->getElement('//unp-product-selection//unp-product-tile//h1[text()="Auswertungen Online"]', null, 'xpath') != null) {
                $this->exts->click_element('//unp-product-selection//unp-product-tile//h1[text()="Auswertungen Online"]');
                sleep(5);
            }
 
            $this->processInvoices();
        }
    }

    public function switchToFrame($query_string)
    {
        $this->exts->log(__FUNCTION__ . " Begin with " . $query_string);
        $frame = null;
        if (is_string($query_string)) {
            $frame = $this->exts->queryElement($query_string);
        }

        if ($frame != null) {
            $frame_context = $this->exts->get_frame_excutable_context($frame);
            if ($frame_context != null) {
                $this->exts->current_context = $frame_context;
                return true;
            }
        } else {
            $this->exts->log(__FUNCTION__ . " Frame not found " . $query_string);
        }

        return false;
    }

    public $totalInvoices = 0;

    private function processInvoices()
    {
        sleep(10);
        $this->exts->capture("4-invoices-page");

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->click_element('p-dropdownitem [aria-label="Abrechnung"]');
        sleep(3);
        $this->exts->capture("4-invoices-page");
        $month_limitation_count = 1;

        $count_years = count($this->exts->getElements('unp-aol-overview > div > p-accordion p-accordiontab'));
        for ($y = 0; $y < $count_years; $y++) {
            if ($restrictPages != 0 && $this->totalInvoices >= 100) {
                return;
            }

            $selected_year = $this->exts->getElements('unp-aol-overview > div > p-accordion p-accordiontab')[$y];
            if ($this->exts->getElement('unp-lazy-load .row', $selected_year) == null) {
                $expand_button = $this->exts->getElement('[aria-expanded="false"] .pi-chevron-right, a.p-accordion-header-link', $selected_year);
                $this->exts->click_element($expand_button);
                sleep(10);
            }

            $count_months = count($this->exts->getElements('p-accordiontab', $selected_year));
            $this->exts->log("count_months  :  " . $count_months);

            for ($m = 0; $m < $count_months; $m++) {
                $selected_month = $this->exts->getElements('p-accordiontab', $selected_year)[$m];
                $month_label = $this->exts->extract('.p-accordion-header-text:nth-child(3)', $selected_month, 'innerText');
                $this->exts->log("SELECTING month  :  " . $month_label);
                if ($this->exts->getElement('.ng-trigger-tabContent[aria-hidden="false"]', $selected_month) == null) {
                    $expand_month = $this->exts->getElement('[aria-expanded="false"] .pi-chevron-right, a.p-accordion-header-link', $selected_month);
                    $this->exts->click_element($expand_month);
                    sleep(8);
                }
                $invoices = count($this->exts->getElements('unp-aol-report-box unp-card[id], unp-aol-report-row > [id]', $selected_month));

                $this->exts->log("invoice count  :  " . $invoices);

                for ($i = 0; $i < $invoices; $i++) {
                    $invoice_element = $this->exts->getElements('unp-aol-report-box unp-card[id], unp-aol-report-row > [id]', $selected_month)[$i];
                    $invoiceName = $invoice_element->getAttribute('id');
                    $this->exts->log("invoiceName  :  " . $invoiceName);

                    $invoiceDate = '';
                    $invoiceAmount = '';
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    $this->isNoInvoice = false;

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->click_element($invoice_element);
                        sleep(6);
                        $this->switchToFrame('iframe[src*="/PDF"]');
                        $this->exts->click_element('button#download');

                        $this->exts->wait_and_check_download('pdf');
                        $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                        if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                            $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                            $this->totalInvoices++;
                        } else {
                            $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                        }

                        $this->exts->switchToDefault();
                        $this->exts->click_element('.icon-chevron-left');
                        sleep(3);
                    }
                }

                if ($this->exts->config_array['restrictPages'] !== '0' && $month_limitation_count >= 3) {
                    return;
                }
                $month_limitation_count++;
            }
        }
    }
}

$portal = new PortalScriptCDP("optimized-chrome-v2", 'LR', '2673461', 'aW5mb0Bway1rb2VuaWcuZGU=', 'S2F0cmluMTgxMDM2');
$portal->run();
