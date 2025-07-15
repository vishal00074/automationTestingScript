<?php // replace exists with isExist custom function

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

    // Server-Portal-ID: 6421 - Last modified: 03.07.2025 14:50:38 UTC - User: 1

    public $username_selector = 'div.login-form form input#loginemail';
    public $password_selector = 'div.login-form form input#loginpassword';
    public $remember_me_selector = 'input#rememberMe';
    public $submit_login_selector = 'div.login-form form button[type="submit"]';

    public $username_selector_1 = 'form#log_user input[name="user"]';
    public $password_selector_1 = 'form#log_user input[name="pass"]';
    public $remember_me_selector_1 = '';
    public $submit_login_selector_1 = 'form#log_user [type="submit"]';

    public $check_login_failed_selector = 'form#log_user div.error, div.login-form p[class*="is-invalid"]';
    public $check_login_success_selector = 'span.s-header-user__avatar, div.bottom-nav-part img[src*="userAvatar"], button[data-identifier="app-nav__notifications"]';
    public $custom_url = "https://webundstyle.eu.teamwork.com";

    public $isNoInvoice = true;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);

        if (isset($this->exts->config_array["custom_url"]) && !empty($this->exts->config_array["custom_url"])) {
            $this->custom_url = trim($this->exts->config_array["custom_url"]);
        } else if (isset($this->exts->config_array["customUrl"]) && !empty($this->exts->config_array["customUrl"])) {
            $this->custom_url = trim($this->exts->config_array["customUrl"]);
        } else if (empty($this->custom_url)) {
            $this->custom_url = $this->custom_url;
        }


        if (strpos($this->custom_url, 'https://') === false && strpos($this->custom_url, 'http://') === false) {
            $this->custom_url = 'https://' . $this->custom_url;
        }


        $this->exts->log('custom_url    ' . $this->custom_url);


        if (strpos($this->custom_url, '?code=') !== false) {
            $this->custom_url = end(explode('?code=', $this->custom_url));
            if (strpos($this->custom_url, '%3A%2F%2F') !== false) {
                $this->custom_url = urldecode($this->custom_url);
            }
        }

        if (strpos($this->custom_url, '%') !== false) {
            $this->custom_url = preg_replace('/[\%]/', '', $this->custom_url);
        }

        if (substr($this->custom_url, -1) == '/') {
            $this->custom_url = substr($this->custom_url, 0, -1);
        }

        $this->baseUrl = $this->loginUrl = $this->custom_url;
        $this->exts->log('custom_url: ' . $this->custom_url);


        $this->exts->openUrl($this->baseUrl);
        sleep(1);

        $this->exts->openUrl($this->baseUrl);
        sleep(25);

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->capture('not-logged-cookie');
            $this->exts->openUrl($this->loginUrl);
            sleep(15);

            if (!$this->isExists('div.login-form form, form#log_user')) {
                $this->exts->openUrl('https://www.teamwork.com/launchpad/login?continue=/launchpad/welcome');
                sleep(15);
            }

            if ($this->isExists('li.products-option a.btn.btn-green')) {
                $this->exts->moveToElementAndClick('li.products-option a.btn.btn-green');
                sleep(15);
            }
            $this->checkFillLogin();
            sleep(20);
            $this->checkFillTwoFactor();
            if ($this->isExists('div.w-product-list a[href="/"]')) {
                $this->exts->moveToElementAndClick('div.w-product-list a[href="/"]');
                sleep(5);
            }
            if ($this->isExists('#gdprConsent button')) {
                $this->exts->moveToElementAndClick('#gdprConsent button');
                sleep(3);
            }
            if (strpos($this->exts->getUrl(), 'teamwork.com/launchpad/welcome') !== false) {
                $this->exts->openUrl(explode('/launchpad/', $this->exts->getUrl())[0]);
                sleep(15);
            }
        }

        // then check user logged in or not
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("login-success");

            // Open invoices url and download invoice
            $this->processBeforeDownloadInvoice();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            if ($this->isExists('div.page-account-not-set-up')) {
                $this->exts->account_not_ready();
            }

            if ($this->isExists('div.page-login form [for="industry-category"]') || $this->isExists('div.page-login form [for="company-size"]')) {
                $this->exts->account_not_ready();
            }
            if (stripos($this->exts->extract($this->check_login_failed_selector, null, 'innerText'), 'the correct email or password') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function isExists($selector = '')
    {
        $safeSelector = addslashes($selector);
        $this->exts->log('Element:: ' . $safeSelector);
        $isElement = $this->exts->execute_javascript('!!document.querySelector("' . $safeSelector . '")');
        if ($isElement) {
            $this->exts->log('Element Found');
            return true;
        } else {
            $this->exts->log('Element not Found');
            return false;
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
            sleep(5);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector);
        } else if ($this->isExists($this->password_selector_1)) {
            sleep(3);
            $this->exts->capture("2-login-page");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector_1, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector_1, $this->password);
            sleep(5);

            if ($this->remember_me_selector != '')
                $this->exts->moveToElementAndClick($this->remember_me_selector_1);
            sleep(2);

            $this->exts->capture("2-login-page-filled");
            $this->exts->moveToElementAndClick($this->submit_login_selector_1);
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        $two_factor_selector = 'form.w-login-form input.w-auth-code__input';
        $two_factor_message_selector = 'form.w-login-form p.w-page-header__description';
        $two_factor_submit_selector = 'form.w-login-form button[type="submit"]';

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
                $this->exts->getElement($two_factor_selector)->moveToElementAndType($two_factor_code);

                $this->exts->log("checkFillTwoFactor: Clicking submit button.");
                sleep(3);
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

                if ($this->exts->getElement($two_factor_selector) == null) {
                    $this->exts->log("Two factor solved");
                } else if ($this->exts->two_factor_attempts < 3) {
                    $this->exts->notification_uid = '';
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

    private function processBeforeDownloadInvoice()
    {
        $current_url = $this->exts->getUrl();
        $paths = explode('/', $current_url);
        $current_domain = $paths[0] . '//' . $paths[2];

        $this->exts->openUrl($current_domain . '/app/settings/invoicehistory');
        sleep(10);
        $this->exts->refresh();
        sleep(20);
        $this->exts->waitTillPresent('iframe[data-test-id="legacy-app-iframe"]', 20);
        $this->switchToFrame('iframe[data-test-id="legacy-app-iframe"]');
        $this->processInvoices();
        $this->exts->switchToDefault();

        $this->exts->openUrl($current_domain . '#settings/subscription');
        sleep(20);
        $this->exts->refresh();
        sleep(20);
        $this->processSubscriptionInvoice();

        $this->exts->openUrl($current_domain . '/desk/settings/billing');
        sleep(20);
        $this->exts->moveToElementAndClick('div.subscription-summary-controls button.billing-button.btn--clr-default');
        sleep(15);
        $this->processDeskBilling();

        $this->exts->openUrl($current_domain . '/spaces/settings/subscription');
        sleep(20);
        $this->processSpacesBilling();
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
        sleep(5);
        $this->exts->waitTillPresent('div.section-settings-invoicehistory table.subscriptionLog tbody tr', 30);
        $this->exts->capture("4-invoicehistory-page");
        $invoices = [];

        $rows_len = count($this->exts->getElements('div.section-settings-invoicehistory table.subscriptionLog tbody tr'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('div.section-settings-invoicehistory table.subscriptionLog tbody tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 7 && $this->exts->getElement('a[data-bind*="OnClickDownloadInvoice"]', $row) != null) {
                $download_button = $this->exts->getElement('a[data-bind*="OnClickDownloadInvoice"]', $row);
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                if (strpos($invoiceDate, 'T')) {
                    $invoiceDate = trim(explode('T', $invoiceDate)[0]);
                } else {
                    $invoiceDate = trim(explode(' ', $invoiceDate)[0]);
                }
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[5]->getAttribute('innerText'))) . ' USD';

                $this->isNoInvoice = false;

                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }

                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }

                $this->exts->wait_and_check_download('pdf');

                $downloaded_file = $this->exts->find_saved_file('pdf');
                sleep(1);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $invoiceFileName = basename($downloaded_file);
                    $invoiceName = explode('.pdf', $invoiceFileName)[0];
                    $invoiceName = explode('(', $invoiceName)[0];
                    $invoiceName = str_replace(' ', '', $invoiceName);
                    $this->exts->log('Final invoice name: ' . $invoiceName);
                    $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                    @rename($downloaded_file, $this->exts->config_array['download_folder'] . $invoiceFileName);

                    if ($this->exts->invoice_exists($invoiceName)) {
                        $this->exts->log('Invoice existed ' . $invoiceFileName);
                    } else {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                        sleep(1);
                    }
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }

    private function processSubscriptionInvoice()
    {
        sleep(25);
        $this->exts->capture("4-subscription-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div#perUserSubscripion table >tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a[href*="/invoice/"][href*="pdf"]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoice/"][href*="pdf"]', $row)->getAttribute("href");
                $invoiceName = trim($tags[1]->getAttribute('innerText'));
                if ($invoiceName == '') {
                    $invoiceName = explode(
                        '.pdf',
                        array_pop(explode('/invoice/', $invoiceUrl))
                    )[0];
                }
                $invoiceDate = trim($tags[0]->getAttribute('innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' USD';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd M y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processDeskBilling()
    {
        $this->exts->capture("4-desk-invoices-page");
        $invoices = [];

        $rows_len = count($this->exts->getElements('div.invoices-modal-content table.body-table tr'));
        for ($i = 0; $i < $rows_len; $i++) {
            $row = $this->exts->getElements('div.invoices-modal-content table.body-table tr')[$i];
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('div[columnname="download"] button', $row) != null) {
                $download_button = $this->exts->getElement('div[columnname="download"] button', $row);
                $as = $this->exts->getElement('div[columnname="download"]', $row);

                $invoiceName = trim($as->getAttribute('id'));
                $invoiceUrl = 'https://checkout.teamwork.com/invoice/' . $invoiceName . '.pdf';
                $invoiceDate = trim($as->getAttribute('date'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $as->getAttribute('total'))) . ' USD';
                $this->isNoInvoice = false;

                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                $invoice['invoiceDate'] = $this->exts->parse_date($invoiceDate, 'F d Y', 'Y-m-d');
                $this->exts->log('Date parsed: ' . $invoiceDate);

                if ($this->exts->document_exists($invoiceFileName)) {
                    continue;
                }

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
                sleep(1);

                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $downloaded_file);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }

    private function processSpacesBilling()
    {
        $this->exts->capture("4-spaces-invoices-page");
        $invoices = [];

        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        if ($restrictPages == 0) {
            $this->exts->moveToElementAndClick('button.show-all__chevron');
        }

        $rows = $this->exts->getElements('table.invoices-list__table tbody tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if (count($tags) >= 5 && $this->exts->getElement('a[href*="/invoice/"]', $row) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="/invoice/"]', $row)->getAttribute('href');
                $invoiceName = trim($this->exts->extract('div', $tags[1], 'innerText'));
                $invoiceDate = trim($this->exts->extract('a[href*="/invoice/"]', $row, 'innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' USD';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'M d, Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
