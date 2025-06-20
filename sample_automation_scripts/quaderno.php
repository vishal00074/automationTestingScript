<?php // handle emapty invoice name case updated two fa code added and updated download code

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
    // Server-Portal-ID: 17791 - Last modified: 26.06.2024 08:52:58 UTC - User: 1

    /*Define constants used in script*/
    public $baseUrl = 'https://quaderno.io/';
    public $loginUrl = 'https://quadernoapp.com/login';
    public $invoicePageUrl = 'https://fxforaliving.quadernoapp.com/invoices';
    public $billingPageUrl = 'https://ninive-7362.quadernoapp.com/settings/payment-history';

    public $username_selector = 'input#user_email';
    public $password_selector = 'input#user_password';
    public $remember_me_selector = '';
    public $submit_login_selector = 'input[type="submit"]';

    public $check_login_failed_selector = 'div.alerts.error';
    public $check_login_success_selector = 'a[href*="/logout"]';

    public $isNoInvoice = true;
    public $only_sales_invoice = 0;
    public $restrictPages = 3;

    public $total_invoices = 0;
    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->disable_extensions();
        $this->exts->log('Begin initPortal ' . $count);
        sleep(1);
        $this->only_sales_invoice = isset($this->exts->config_array["only_sales_invoice"]) ? (int)$this->exts->config_array["only_sales_invoice"] : $this->only_sales_invoice;
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log('restrictPages:: ' .  $this->restrictPages);
        $this->exts->log('only_sales_invoice:: ' .  $this->only_sales_invoice);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        // Load cookies
        // $this->exts->loadCookiesFromFile();
        if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
            $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
        }
        sleep(2);
        $this->exts->capture('1-init-page');

        // If user hase not logged in from cookie, clear cookie, open the login url and do login
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(5);
            $this->checkFillLogin();
            sleep(20);
        }


        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $currentUrl = $this->exts->getUrl();
            $this->invoicePageUrl = $currentUrl . '/invoices';
            $this->billingPageUrl = $currentUrl . '/settings/payment-history';

            if ($this->only_sales_invoice == 1) {
                // Open invoices url and download invoice
                $this->exts->openUrl($this->invoicePageUrl);
                sleep(7);
                $this->dateRange();
                $this->processInvoices();
            } else {
                // Open invoices url and download invoice
                $this->exts->openUrl($this->invoicePageUrl);
                $this->dateRange();
                $this->processInvoices();

                $this->exts->openUrl($this->billingPageUrl);
                $this->processBilling();
            }

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }
            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');

            $error_text = strtolower($this->exts->extract('div.banner p.text-body'));
            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);

            if (strpos(strtolower($this->exts->extract('div.alert-message')), 'an error processing the form') !== false) {
                $this->exts->loginFailure(1);
            } else if (!filter_var($this->username, FILTER_VALIDATE_EMAIL)) {
                $this->exts->log("Username is not a valid email address");
                $this->exts->loginFailure(1);
            } else if (stripos($error_text, strtolower('Invalid email or password')) !== false) {
                $this->exts->capture('login-failed-confirmed');
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->getElement($this->username_selector) != null) {
            $this->exts->capture("2-login-page");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);
            $this->exts->capture("2-login-page-filled");
            sleep(5);
            $this->exts->moveToElementAndClick($this->submit_login_selector);
            sleep(10);
            if ($this->exts->getElement($this->password_selector) != null) {
                $this->exts->openUrl($this->loginUrl);
                sleep(5);
                if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                    $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                }
                $this->checkFillLoginUndetected();
            }
            // try with js
            if ($this->exts->getElement($this->password_selector) != null) {
                sleep(5);
                if ($this->exts->exists('button#onetrust-accept-btn-handler')) {
                    $this->exts->moveToElementAndClick('button#onetrust-accept-btn-handler');
                }
                $this->checkFillLoginJs();
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillLoginJs()
    {
        $this->exts->log(__FUNCTION__);
        $this->exts->log("Enter Username");
        $this->exts->execute_javascript("document.querySelector('input#user_email').value = " . json_encode($this->username) . ";");
        sleep(2);
        $this->exts->log("Enter Password");
        $this->exts->execute_javascript("document.querySelector('input#user_password').value =" . json_encode($this->username) . ";");
        sleep(2);
        $this->exts->execute_javascript("document.querySelector('button[name=\"commit\"], input[name=\"commit\"]').click();");
        sleep(10);
    }

    private function checkFillLoginUndetected()
    {
        $this->exts->waitTillPresent($this->username_selector);

        $this->exts->type_key_by_xdotool("Ctrl+t");
        sleep(13);

        $this->exts->type_key_by_xdotool("F5");

        sleep(5);

        $this->exts->type_text_by_xdotool($this->loginUrl);
        $this->exts->type_key_by_xdotool("Return");
        sleep(15);
        for ($i = 0; $i < 13; $i++) {
            $this->exts->type_key_by_xdotool('Tab');
            sleep(1);
        }
        $this->exts->log("Enter Username");
        $this->exts->type_text_by_xdotool($this->username);
        sleep(2);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->log("Enter Password");
        $this->exts->type_text_by_xdotool($this->password);
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
        $this->exts->type_key_by_xdotool("Return");
        sleep(15);
    }

    private function disable_extensions()
    {
        $this->exts->openUrl('chrome://extensions/');
        sleep(2);
        $this->exts->execute_javascript("
        let manager = document.querySelector('extensions-manager');
        if (manager && manager.shadowRoot) {
            let itemList = manager.shadowRoot.querySelector('extensions-item-list');
            if (itemList && itemList.shadowRoot) {
                let items = itemList.shadowRoot.querySelectorAll('extensions-item');
                items.forEach(item => {
                    let toggle = item.shadowRoot.querySelector('#enableToggle[checked]');
                    if (toggle) toggle.click();
                });
            }
        }
    ");
    }

    private function dateRange()
    {
        $this->exts->waitTillPresent('button.filter-by-date');

        $this->exts->capture('select-date-range');

        if ($this->exts->exists('button.filter-by-date')) {
            $this->exts->moveToElementAndClick('button.filter-by-date');
            sleep(4);
        }

        $this->changeSelectbox('select.date-selector', 'custom_period');

        sleep(5);
        if ($this->exts->querySelector('button#apply-monthly-period') != null) {
            $this->exts->moveToElementAndClick('button#apply-monthly-period');
            sleep(5);
        }
        $selectDate = new DateTime();

        if ($this->restrictPages == 0) {
            // select date
            $selectDate->modify('-3 years');

            $day = $selectDate->format('d');
            $month = $selectDate->format('m');
            $year = $selectDate->format('Y');

            $this->exts->log('3 years previous date:: ' . $day . '-' . $month . '-' . $year);

            //select day
            $this->changeSelectbox('select[id*="filter_from_date_3i"]', $day);
            sleep(2);
            //select month
            $this->changeSelectbox('select[id*="filter_from_date_2i"]', $month);
            sleep(2);
            //select year
            $this->changeSelectbox('select[id*="filter_from_date_1i"]', $year);
            sleep(2);
            $this->exts->capture('date-range-3-years');
        } else {
            // select date
            $selectDate->modify('-3 months');

            $day = $selectDate->format('d');
            $month = $selectDate->format('m');
            $year = $selectDate->format('Y');

            $this->exts->log('3 months previous date:: ' . $day . '-' . $month . '-' . $year);

            //select day
            $this->changeSelectbox('select[id*="filter_from_date_3i"]', $day);
            sleep(2);
            //select month
            $this->changeSelectbox('select[id*="filter_from_date_2i"]', $month);
            sleep(2);
            //select year
            $this->changeSelectbox('select[id*="filter_from_date_1i"]', $year);
            sleep(2);
            $this->exts->capture('date-range-3-months');
        }
    }
    private function changeSelectbox($selectbox, $value)
    {
        $this->exts->execute_javascript(
            'let selectBox = document.querySelector("' . addslashes($selectbox) . '");
        if (selectBox) {
            selectBox.value = "' . addslashes($value) . '";
            selectBox.dispatchEvent(new Event("change"));
        }'
        );
    }


    private function processInvoices($pageCount = 0)
    {
        sleep(15);
        // load all invoices 
        for ($i = 0; $i < 3; $i++) {
            if ($this->exts->querySelector('a#next-page-link') != null) {
                $this->exts->moveToElementAndClick('a#next-page-link');
                sleep(5);
            } else {
                break;
            }
        }

        $this->exts->waitTillPresent('main#main ol > li');
        $this->exts->capture("4-invoices-page");
        $invoices = [];
        $rows = $this->exts->getElements('main#main ol > li');
        foreach ($rows as $row) {
            $invoiceLink = $this->exts->getElement("div.list-item a[href*='/invoices/']", $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract("div.list-item a[href*='/invoices/']", $row);
                $invoiceDate = $this->exts->extract("div:nth-child(3)", $row);
                $invoiceAmount1 = $this->exts->extract("div:nth-child(5)", $row);
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount1)) . ' EUR';
                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl . ".pdf"
                ));

                $this->isNoInvoice = false;
            }
        }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        sleep(5);
        foreach ($invoices as $invoice) {
            if ($this->total_invoices >= 100) {
                // stop the function
                return;
            }
            $this->exts->log('total_invoices:: ' . $this->total_invoices);

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
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
                $this->total_invoices++;
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }

    private function processBilling()
    {
        sleep(25);
        $this->exts->capture("4-billing-page");
        $invoices = [];
        $rows = $this->exts->getElements('table tbody tr');
        foreach ($rows as $key =>  $row) {
            $rowNum = $key + 1;
            $invoiceBtn = 'table tbody tr:nth-child(' . $rowNum . ')';
            $invoiceName = $this->exts->extract('td:nth-child(3)', $invoiceBtn);
            $invoiceDate = $this->exts->extract('td:nth-child(1)', $invoiceBtn);
            $invoiceAmount1 = $this->exts->extract('td:nth-child(2)', $invoiceBtn);
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $invoiceAmount1)) . ' EUR';

            $this->isNoInvoice = false;

            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' .  $invoiceName);
            $this->exts->log('invoiceDate: ' . $invoiceDate);
            $this->exts->log('invoiceAmount: ' . $invoiceAmount);

            $invoiceFileName = !empty($invoiceName) ?  $invoiceName . '.pdf' : '';
            $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' .  $invoiceDate);

            if ($this->exts->querySelector($invoiceBtn) != null) {
                $this->exts->moveToElementAndClick($invoiceBtn);
                sleep(10);
            }
            $this->exts->waitTillPresent('div#actions a[href*="pdf"]');

            $downloaded_file = $this->exts->click_and_download('div#actions a[href*="pdf"]', 'pdf', $invoiceFileName);
            sleep(2);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice('',  $invoiceDate, $invoiceAmount, $downloaded_file);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
            $this->exts->openUrl($this->billingPageUrl);
            sleep(12);
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
