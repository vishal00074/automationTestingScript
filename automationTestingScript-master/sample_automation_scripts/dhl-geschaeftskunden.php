<?php// updated download code

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

    // Server-Portal-ID: 6096 - Last modified: 25.04.2025 13:10:19 UTC - User: 1

    public $baseUrl = 'https://geschaeftskunden.dhl.de';
    public $username_selector = 'input[name*="username"]';
    public $password_selector = 'input[name*="password"]';
    public $submit_login_selector = 'button.submit.login, button#button-loginSubmit, div#kc-form-buttons';

    public $check_login_failed_selector = '[id*="pt:dmaIl:pglError"] > div.dhl-errors div, .af_message_detail, div[data-testid="password-error"]';
    public $check_login_success_selector = '.username-container + .af_panelList li [id*="admin"], [data-testid="myAccount.logout"]';

    public $restrictPages = 3;
    public $daily_closing_list = 0;
    public $isNoInvoice = true;
    public $download_report = 0;
    public $shipment_tracking = 0;
    public $only_download_report = 0;

    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        $this->daily_closing_list = isset($this->exts->config_array["daily_closing_list"]) ? (int) @$this->exts->config_array["daily_closing_list"] : $this->daily_closing_list;
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : $this->restrictPages;
        $this->download_report = isset($this->exts->config_array["download_report"]) ? (int) @$this->exts->config_array["download_report"] : $this->download_report;
        $this->shipment_tracking = isset($this->exts->config_array["shipment_tracking"]) ? (int) @$this->exts->config_array["shipment_tracking"] : $this->shipment_tracking;
        $this->only_download_report = isset($this->exts->config_array["only_download_report"]) ? (int) @$this->exts->config_array["only_download_report"] : $this->only_download_report;

        $this->exts->log('restrictPages '. $this->restrictPages);
        $this->exts->log('daily_closing_list '. $this->daily_closing_list);
        $this->exts->log('download_report '. $this->download_report);
        $this->exts->log('shipment_tracking '. $this->shipment_tracking);
        $this->exts->log('only_download_report '. $this->only_download_report);

        // Load cookies
        $this->exts->loadCookiesFromFile();
        sleep(1);
        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        if ($this->exts->exists('button#accept-recommended-btn-handler')) {
            $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
            sleep(3);
        }
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
            sleep(3);
        }
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->openUrl($this->baseUrl);
            sleep(10);

            for ($i = 0; $i < 3; $i++) {
                if (count($this->exts->getElements('body div')) == 0) {
                    $this->exts->refresh();
                    sleep(10);
                } else {
                    break;
                }
            }
            if ($this->exts->exists('button#accept-recommended-btn-handler')) {
                $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
                sleep(3);
            }

            if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                $this->exts->click_by_xdotool('button#onetrust-accept-btn-handler');
                sleep(3);
            }

            if ($this->exts->exists('div.login-module-container button[data-testid="noName"]')) {
                $this->exts->click_by_xdotool('div.login-module-container button[data-testid="noName"]');
                sleep(10);
            }

            if ($this->exts->exists('iframe.keycloakLogin')) {
                $this->switchToFrame('iframe.keycloakLogin');
                sleep(5);
            }

            // sometimes the page can't load login frame, refresh page
            for ($i = 0; $i < 2; $i++) {
                if (!$this->exts->exists($this->password_selector)) {
                    $this->exts->refresh();
                    sleep(10);
                    if ($this->exts->exists('div.login-module-container button[data-testid="noName"]')) {
                        $this->exts->click_by_xdotool('div.login-module-container button[data-testid="noName"]');
                        sleep(10);
                    }
                } else {
                    break;
                }
            }

            $this->exts->capture('after-reload-page');
            if (!$this->exts->exists($this->password_selector)) {
                $this->exts->switchToDefault();
            }
            // end refresh

            $this->checkFillLogin();
            sleep(20);

            if ($this->exts->exists($this->password_selector)) {
                $this->checkFillLogin();
                sleep(30);
            }

            $this->checkFillTwoFactor();
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            if ($this->exts->exists('button#accept-recommended-btn-handler')) {
                $this->exts->click_by_xdotool('button#accept-recommended-btn-handler');
                sleep(1);
            }
            if ($this->exts->exists('button.abort.af_commandButton')) {
                $this->exts->click_by_xdotool('button.abort.af_commandButton');
                sleep(1);
            }

            // Open invoices url and download invoice
            $open_invoices_link = $this->exts->getElementByText('.horizontal-navigation-sub .af_panelList a, .af_panelGroupLayout  a[id*="subsubtop"]', ['Rechnungssuche', 'Bill']);
            if ($open_invoices_link != null) {
                try {
                    $this->exts->log('Click Billing button');
                    $open_invoices_link->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click Billing button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$open_invoices_link]);
                }
            }
            sleep(15);
            if (!$this->exts->exists('[id*="BillingPeriodForm"] select[name*="socPeriod"]')) {
                $this->exts->update_process_lock();
                $open_invoices_link = $this->exts->getElementByText('.horizontal-navigation-sub .af_panelList a, .af_panelGroupLayout  a[id*="subsubtop"]', ['Rechnungssuche', 'Bill'], null, true);
                if ($open_invoices_link != null) {
                    try {
                        $this->exts->log('Click Billing button');
                        $open_invoices_link->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click Billing button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$open_invoices_link]);
                    }
                }
                sleep(15);
            }
            if ($this->only_download_report == 0) {
                if ((int) $this->restrictPages == 0) {
                    //Three months
                    //$this->exts->changeSelectbox('[id*="BillingPeriodForm"] select[name*="socPeriod"]', '2');
                    $this->exts->execute_javascript("
                var selectBox = document.querySelector('[id*=\"BillingPeriodForm\"] select[name*=\"socPeriod\"]');
                selectBox.value = '2';
                selectBox.dispatchEvent(new Event('change', { bubbles: true }));
            ");

                    sleep(5);
                } else {
                    //1 month
                    //$this->exts->changeSelectbox('[id*="BillingPeriodForm"] select[name*="socPeriod"]', '1');
                    $this->exts->execute_javascript("
                var selectBox = document.querySelector('[id*=\"BillingPeriodForm\"] select[name*=\"socPeriod\"]');
                selectBox.value = '1';
                selectBox.dispatchEvent(new Event('change', { bubbles: true }));
            ");

                    sleep(5);
                }
                $this->exts->click_by_xdotool('[id*="BillingPeriodForm"] button[id*="Search"]');
                $this->processInvoices();

                if ((int) $this->restrictPages == 0 && $this->exts->exists('a[id*=":np:1:cni::disclosureAnchor"]')) {
                    $this->exts->click_by_xdotool('a[id*=":np:1:cni::disclosureAnchor"]');
                    sleep(5);

                    for ($i = 1; $i <= 8; $i++) {
                        $start_str = 3 * $i;
                        $end_str = 3 * ($i + 1);
                        $months[] = array(
                            date('d.m.Y', strtotime('- ' . $end_str . ' Months')),
                            date('d.m.Y', strtotime('- ' . $start_str . ' Months'))
                        );
                    }
                    $this->exts->log('Months - ' . print_r($months, true));
                    foreach ($months as $month) {
                        $this->exts->moveToElementAndType('input[name*="invoiceDateFrom:iComplex"]', $month[0]);
                        sleep(5);

                        $this->exts->moveToElementAndType('input[name*="invoiceDateTo:iComplex"]', $month[1]);
                        sleep(5);

                        $this->exts->moveToElementAndType('input[name*="invoiceDateFrom:iComplex"]', $month[0]);
                        sleep(5);

                        $this->exts->click_by_xdotool('[id*="BillingCalendarForm"] button[id*=":cbSearch"]');
                        $this->processInvoices();
                    }
                }

                $this->exts->openUrl('https://geschaeftskunden.dhl.de/billing/invoice/overview');
                sleep(15);

                $this->exts->click_by_xdotool('div[data-testid="date-range-input-billingView-filter-dateRange"]');
                sleep(5);
                if ((int) $this->restrictPages == 0) {
                    //Three months
                    //$this->exts->changeSelectbox('select[data-testid="billingView-filter-dateRange-footer-presetRangeSelector"]', '2');
                    $this->exts->execute_javascript("
                var selectBox = document.querySelector('select[data-testid=\"billingView-filter-dateRange-footer-presetRangeSelector\"]');
                selectBox.value = '2';
                selectBox.dispatchEvent(new Event('change', { bubbles: true }));
            ");

                    sleep(5);
                } else {
                    //1 month
                    //$this->exts->changeSelectbox('select[data-testid="billingView-filter-dateRange-footer-presetRangeSelector"]', '1');

                    $this->exts->execute_javascript("
                var selectBox = document.querySelector('select[data-testid=\"billingView-filter-dateRange-footer-presetRangeSelector\"]');
                selectBox.value = '1';
                selectBox.dispatchEvent(new Event('change', { bubbles: true }));
            ");
                    sleep(5);
                }
                $this->exts->click_by_xdotool('button#button-billingView-filter-dateRange-footer-submit');
                $this->processBillingOverview();
            }

            //Download Daily Closing List
            if ((int) @$this->daily_closing_list == 1) {
                $this->exts->openUrl('https://geschaeftskunden.dhl.de/content/vls/gw/vlsweb/ArchiveManifest');
                sleep(15);
                if ($this->exts->getElement($this->check_login_success_selector) == null) {
                    $this->checkFillLogin();
                    sleep(20);
                }
                $this->processDailyClosingList();
            }

            //Download Report List
            if ((int) @$this->download_report == 1 || $this->only_download_report == 1) {
                $this->exts->openUrl('https://geschaeftskunden.dhl.de/reportingproactive/report/download/overview');
                sleep(15);
                if ($this->exts->getElement($this->check_login_success_selector) == null) {
                    $this->checkFillLogin();
                    sleep(20);
                }

                $this->processReportDownloadList();
            }

            //Download Status-to-Shipment
            if ((int) @$this->shipment_tracking == 1) {
                $this->exts->openUrl('https://geschaeftskunden.dhl.de/content/scc/shipmentlist');
                sleep(15);
                if ($this->exts->getElement($this->check_login_success_selector) == null) {
                    $this->checkFillLogin();
                    sleep(20);
                }
                if ($this->exts->getElement('iframe[src*="/shipmentlist"]') != null) {
                    $this->switchToFrame('iframe[src*="/shipmentlist"]');
                    sleep(2);
                }
                $this->processShipmentTracking();
                //Process international shipment list
                $this->exts->click_by_xdotool('a[data-rb-event-key="2"]');
                sleep(15);
                $this->processShipmentTracking();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed' . $this->exts->getUrl());
            $this->exts->log(__FUNCTION__ . '::Use login failed' . $this->exts->extract('.af_showDetailFrame_content div.form'));
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"], div.alert-error')), 'deaktiviert') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"], div.alert-error')), 'ist abgelaufen') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p, div.dhl-errors div, div.alert-error')), 'benutzername und/oder passwort ung') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p, div.dhl-errors div, div.alert-error')), 'invalid username and/or password') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'ist abgelaufen') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'valid username and password combination') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'da sie diesen seit mehr als 120 tagen nicht verwendet haben') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'ihr benutzer ist aufgrund') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('.af_showDetailFrame_content div.form')), 'neues passwort festlegen') !== false) {
                $this->exts->account_not_ready();
            } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'sie haben zum') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'mal keine g') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'ltige kombination f') !== false || strpos(strtolower($this->exts->extract('div[data-testid="login-messages-warning-textoutput"] p')), 'r benutzername und passwort eingegeben') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('[data-testid="login-messages-warning-textoutput"] p span')), 'benutzer gesperrt') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('div[data-testid="login-messages-error-textoutput"] p')), 'ystembenutzer anzumelden') !== false) {
                $this->exts->loginFailure(1);
            } else if (strpos(strtolower($this->exts->extract('.alert-error .pf-c-alert__title.kc-feedback-text')), 'bitte beachten sie, dass systembenutzer nicht für eine anmeldung zugelassen sind.') !== false) {
                $this->exts->account_not_ready();
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
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(1);

            $this->exts->capture("2-login-page-filled");
            $this->exts->click_by_xdotool($this->submit_login_selector);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'input#otp';
        $two_factor_message_selector = 'span.kc-feedback-text';
        $two_factor_submit_selector = 'button#kc-login';
        $this->exts->waitTillPresent($two_factor_selector, 10);
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
                    $this->checkFillTwoFactor();
                } else {
                    $this->exts->log("Two factor can not solved");
                }
            } else {
                $this->exts->log("Not received two factor code");
            }
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


    private function processInvoices()
    {
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        //$this->exts->changeSelectbox('[id*="dtp2:settings"] select[name*="dtp2:mrs"]', '6');
        $this->exts->execute_javascript("
    var selectBox = document.querySelector('[id*=\"dtp2:settings\"] select[name*=\"dtp2:mrs\"]');
    selectBox.value = '6';
    selectBox.dispatchEvent(new Event('change', { bubbles: true }));
");

        sleep(10);
        $this->exts->capture("4-invoices-page");

        $this->exts->waitTillPresent('table[summary="Rechnugen"] > tbody > tr', 30);
        $rows = count($this->exts->getElements('table[summary="Rechnugen"] > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table[summary="Rechnugen"] > tbody > tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 6 && $this->exts->getElement('img[src*="pdf"]', $tags[5]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('img[src*="pdf"]', $tags[5]);
                $invoiceName = trim($tags[1]->getText());
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $invoiceDate = trim($tags[0]->getText());
                $invoiceAmount = trim($tags[4]->getText());

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
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
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(30);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                    }
                }
            }
        }
    }

    public function processDailyClosingList($paging_counts = 0)
    {
        sleep(25);
        $this->exts->capture("4-daily-closing-page");

        $this->switchToFrame('iframe[src*="/ArchiveManifest?"]');
        $this->exts->waitTillPresent('table.table.mm_datatable > tbody > tr', 30);
        if ($this->exts->exists('table.table.mm_datatable > tbody > tr')) {
            $rows = count($this->exts->getElements('table.table.mm_datatable > tbody > tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->getElements('table.table.mm_datatable > tbody > tr')[$i];
                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 4 && $this->exts->getElement('a.mm_icon_print', $tags[3]) != null) {
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->getElement('a.mm_icon_print', $tags[3]);
                    $invoiceName = trim($tags[0]->getText()) . '-' . trim($tags[1]->getText());
                    $invoiceFileName = '';
                    $invoiceDate = trim($tags[2]->getText());
                    $invoiceAmount = '';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate);
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', '');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceName = basename($downloaded_file, '.pdf');
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                    }
                }
                // close new tab too avoid too much tabs
                $handles = $this->exts->get_all_tabs();
                if (count($handles) > 1) {
                    $switchedtab = $this->exts->switchToTab(end($handles));
                    $this->exts->closeTab($switchedtab);

                    $handles = $this->exts->get_all_tabs();
                    if (count($handles) > 1) {
                        $switchedtab1 = $this->exts->switchToTab(end($handles));
                        $this->exts->closeTab($switchedtab1);
                        $handles = $this->exts->get_all_tabs();
                    }
                    $this->exts->switchToTab($handles[0]);
                    $this->switchToFrame('iframe[src*="/ArchiveManifest?"]');
                    sleep(2);
                }
            }

            $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
            if ($restrictPages == 0 && $paging_count < 50 && $this->exts->querySelector('ul.pagination li.mm_next a') != null) {
                $paging_count++;
                $this->exts->click_by_xdotool('ul.pagination li.mm_next a');
                sleep(5);
                $this->processDailyClosingList($paging_count);
            } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('ul.pagination li.mm_next a') != null) {
                $paging_count++;
                $this->exts->click_by_xdotool('ul.pagination li.mm_next a');
                sleep(5);
                $this->processDailyClosingList($paging_count);
            }
        } else {
            $rows = count($this->exts->getElements('table.table > tbody > tr'));
            for ($i = 0; $i < $rows; $i++) {
                $row = $this->exts->getElements('table.table > tbody > tr')[$i];
                $tags = $this->exts->getElements('td', $row);
                if (count($tags) >= 4 && $this->exts->getElement('button#button-print', $tags[3]) != null) {
                    $this->isNoInvoice = false;
                    $download_button = $this->exts->getElement('button#button-print', $tags[3]);
                    $invoiceName = trim($tags[0]->getText()) . '-' . trim($tags[1]->getText());
                    $invoiceFileName = '';
                    $invoiceDate = trim($tags[2]->getText());
                    $invoiceAmount = '';

                    $this->exts->log('--------------------------');
                    $this->exts->log('invoiceName: ' . $invoiceName);
                    $this->exts->log('invoiceDate: ' . $invoiceDate);
                    $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                    $parsed_date = $this->exts->parse_date($invoiceDate);
                    $this->exts->log('Date parsed: ' . $parsed_date);

                    try {
                        $this->exts->log('Click download button');
                        $download_button->click();
                    } catch (\Exception $exception) {
                        $this->exts->log('Click download button by javascript');
                        $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                    }
                    sleep(5);
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', '');
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $invoiceName = basename($downloaded_file, '.pdf');
                        $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                    }
                }
                // close new tab too avoid too much tabs
                 $handles = $this->exts->get_all_tabs();
                 if (count($handles) > 1) {
                     $switchedtab = $this->exts->switchToTab(end($handles));
                     $this->exts->closeTab($switchedtab);
 
                     $handles = $this->exts->get_all_tabs();
                     if (count($handles) > 1) {
                         $switchedtab1 = $this->exts->switchToTab(end($handles));
                         $this->exts->closeTab($switchedtab1);
                         $handles = $this->exts->get_all_tabs();
                     }
                     $this->exts->switchToTab($handles[0]);
                     $this->switchToFrame('iframe[src*="/ArchiveManifest?"]');
                     sleep(2);
                 }
            }
            if ($rows > 0 && (int) $this->restrictPages == 0 && $this->exts->exists('ul.pagination li.active + li a') && $this->exts->isVisible('ul.pagination li.active + li a')) {
                $this->exts->click_by_xdotool('ul.pagination li.active + li a');
                sleep(15);

                $pageCount++;
                $this->processDailyClosingList($pageCount);
            }
        }
    }

    public function processReportDownloadList()
    {
        $this->exts->capture("4-download-report-page");

        $this->exts->waitTillPresent('table[data-testid="reportsTableDownload"] > tbody > tr', 30);
        $rows = count($this->exts->getElements('table[data-testid="reportsTableDownload"] > tbody > tr'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table[data-testid="reportsTableDownload"] > tbody > tr ')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 8 && $this->exts->getElement('button div[class*="zip"]', $tags[4]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('button div[class*="zip"]', $tags[7]);
                $invoiceName = '';
                $invoiceFileName = '';
                $invoiceDate = trim($tags[1]->getText());
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate);
                $this->exts->log('Date parsed: ' . $parsed_date);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('zip');
                $downloaded_file = $this->exts->find_saved_file('zip', '');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.zip');
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            } else if (count($tags) >= 5 && $this->exts->getElement('button div[class*="zip"]', $tags[4]) != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('button div[class*="zip"]', $tags[4]);
                $invoiceName = '';
                $invoiceFileName = '';
                $invoiceDate = trim(explode('um', $tags[1]->getText())[0]);
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate);
                $this->exts->log('Date parsed: ' . $parsed_date);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->wait_and_check_download('zip');
                $downloaded_file = $this->exts->find_saved_file('zip', '');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceName = basename($downloaded_file, '.zip');
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            }
        }
    }

    private function processBillingOverview($paging_count = 1)
    {

        $this->exts->capture("4-processBillingOverview-page");
        $invoices = [];
        $this->exts->switchToDefault();
        sleep(15);
        $rows = $this->exts->querySelectorAll('[data-testid="billing-table"] table tbody tr');

        $this->exts->log(count($rows));
        foreach ($rows as $row) {
            if ($this->exts->querySelector('td:nth-child(7) button', $row) != null) {
                $download_button = $this->exts->querySelector('td:nth-child(7) button', $row);
                $invoiceName = $this->exts->extract('td:nth-child(3)', $row);
                $invoiceDate = $this->exts->extract('td:nth-child(2)', $row);
                $invoiceAmount = $this->exts->extract('td:nth-child(5)', $row);
                $this->isNoInvoice = false;;
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                $this->exts->execute_javascript("arguments[0].click()", [$download_button]);

                $this->exts->waitTillPresent('.dhlContextmenu-visible button[data-testid="downloadCMLabel-pdf"]', 10);
                if ($this->exts->exists('.dhlContextmenu-visible button[data-testid="downloadCMLabel-pdf"]')) {
                    $invoice_button = $this->exts->querySelector('.dhlContextmenu-visible button[data-testid="downloadCMLabel-pdf"]');
                    $this->exts->execute_javascript("arguments[0].click()", [$invoice_button]);
                }

                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            }
        }

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int) @$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0 && $paging_count < 50 && $this->exts->querySelector('li.next-page-link-active button') != null) {
            $paging_count++;
            $this->exts->click_element('li.next-page-link-active button');
            sleep(5);
            $this->processBillingOverview($paging_count);
        } else if ($restrictPages > 0 && $paging_count < $restrictPages && $this->exts->querySelector('li.next-page-link-active button') != null) {
            $paging_count++;
            $this->exts->click_element('li.next-page-link-active button');
            sleep(5);
            $this->processBillingOverview($paging_count);
        }
    }

    public function processShipmentTracking()
    {
        $this->exts->click_by_xdotool('button[data-testid="shipments-loading-panel-button"]');
        sleep(15);
        $this->exts->click_by_xdotool('[data-testid="dropdownForFilterTypeSTATUS"] button.dropdown-toggle');
        sleep(3);
        $this->exts->click_by_xdotool('input[name="DeliverySuccessful"]');
        sleep(3);
        $this->exts->click_by_xdotool('[data-testid="dropdownForFilterTypeSTATUS"] button[type="submit"]');
        $this->exts->capture("4-download-Status-to-Shipment");

        $this->exts->waitTillPresent('table > tbody > tr.tr-row-open-content', 30);

        $rows = count($this->exts->getElements('table > tbody > tr.tr-row-open-content'));
        for ($i = 0; $i < $rows; $i++) {
            $row = $this->exts->getElements('table > tbody > tr.tr-row-open-content')[$i];
            if ($this->exts->getElement('//*[contains(text(),"Sendungsdetails als PDF")]/./..', $row, 'xpath') != null) {
                $this->isNoInvoice = false;
                $download_button = $this->exts->getElement('//*[contains(text(),"Sendungsdetails als PDF")]/./..', $row, 'xpath');
                $invoiceName = trim($this->exts->extract('[data-testid="metaSendungsnummer"]', $row));
                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : "";
                $invoiceDate = '';
                $invoiceAmount = '';

                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                $parsed_date = $this->exts->parse_date($invoiceDate);
                $this->exts->log('Date parsed: ' . $parsed_date);

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->execute_javascript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                if ($this->exts->getElement('button[data-testid="pdf-export-with-pod"]') != null) {
                    $this->exts->click_by_xdotool('button[data-testid="pdf-export-with-pod"]');
                    sleep(5);
                }
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf');
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceName);
                }
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
