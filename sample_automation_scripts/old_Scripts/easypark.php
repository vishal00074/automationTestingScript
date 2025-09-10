<?php // replace waitTillPresent to waitFor
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

    // Server-Portal-ID: 9754 - Last modified: 23.07.2025 14:29:47 UTC - User: 1

    public $baseUrl = 'https://www.easypark.com/de';
    public $username_selector = 'input#phonenumber, input#userName';
    public $password_selector = 'input#password';
    public $submit_login_selector = 'form#signinform #submit, button#buttonLogin';
    public $check_login_failed_selector = '.swal2-shown .swal2-animate-error-icon';
    public $check_login_success_selector = 'a[href*="/logout"], li#menu-item-signout, a.logout-button, a[href*="/logout"], a.APICA-TEST-USER-AVATAR, .APICA-TEST-SIGNOUT';
    public $isNoInvoice = true;
    public $common_invoice_url = 'https://easypark.de/history/de';
    public $admin_user_specific_url = 'https://easypark.de/business/admin/billing/de';
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

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
        $this->waitFor($this->check_login_success_selector, 10);
        if ($this->exts->getElement($this->check_login_success_selector) == null) {
            $this->exts->log('NOT logged via cookie');
            $this->exts->getElement('//*[@id="header"]/div/header[1]/div[1]/div[2]/div/div[2]/a/span');
            if ($this->exts->exists('//*[@id="header"]/div/header[1]/div[1]/div[2]/div/div[2]/a/span')) {
                $this->exts->click_element('//*[@id="header"]/div/header[1]/div[1]/div[2]/div/div[2]/a/span');
            }
            sleep(5);
            $this->exts->moveToElementAndClick('div [class*="styled__StyledDesktopHeader"]:nth-child(2) a[href*="/auth/?lang"]');
            sleep(5);
            $login_tab = $this->exts->findTabMatchedUrl(['/auth']);
            $this->exts->switchToTab($login_tab);
            // check if account must logout
            if ($this->exts->getElement($this->password_selector) == null) {
                if ($this->exts->exists('p') && strpos(strtolower($this->exts->extract('p')), 'angemeldet als') !== false) {
                    if ($this->exts->exists('button') && strpos(strtolower($this->exts->extract('button')), 'abmelden') !== false) {
                        $this->exts->log('------Click Abmelden button---');
                        $this->exts->moveToElementAndClick('button');
                        sleep(10);
                    }
                }
            }
            $this->waitFor($this->password_selector);
            $this->checkFillLogin();
            sleep(10);

            if ($this->exts->exists('button[data-testid="send-sms-button"]')) {
                $this->exts->moveToElementAndClick('button[data-testid="send-sms-button"]');
                sleep(10);
            } elseif ($this->exts->exists('button[data-testid="send-email-button"]')) {
                $this->exts->moveToElementAndClick('button[data-testid="send-email-button"]');
                sleep(10);
            }
            $this->checkFillTwoFactor();
        }


        // then check user logged in or not
        $this->waitFor($this->check_login_success_selector, 25);
        if ($this->exts->getElement($this->check_login_success_selector) != null) {
            sleep(3);
            $this->exts->log(__FUNCTION__ . '::User logged in');
            $this->exts->capture("3-login-success");

            $this->invoicePage();

            if ($this->isNoInvoice) {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $mes = strtolower($this->exts->extract('form#signinform div.MuiTypography-alignLeft, p#password-helper-text'));
            if ($this->exts->getElement($this->check_login_failed_selector) != null) {
                $this->exts->loginFailure(1);
            } else if (stripos($mes, 'passwor') !== false || strpos($mes, 'wrong username or password') !== false || strpos($mes, 'wrong phone number or password') !== false || strpos($mes, 'oder das passwort ist falsch') !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    private function checkFillLogin()
    {
        if ($this->exts->getElement($this->password_selector) != null) {
            $this->exts->capture("2-login-page");
            $temp_username = str_replace('+', '', $this->username);
            //check username is phonenumber. if username is not a phonenumber, click login with username
            if ($this->exts->exists('button[value="phone"][aria-pressed="true"]') && !is_numeric($temp_username)) {
                $this->exts->moveToElementAndClick('button[value="username"][aria-pressed="false"]');
                sleep(10);
                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector, $this->username);
                sleep(1);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            } else {
                $this->exts->log("Enter Username");
                // $this->exts->moveToElementAndType($this->username_selector, $this->username);
                $this->exts->click_by_xdotool($this->username_selector);
                sleep(1);
                if (strpos($this->username, '+') !== false) {
                    $this->exts->type_key_by_xdotool('BackSpace');
                    sleep(1);
                    $this->exts->type_key_by_xdotool('BackSpace');
                    sleep(1);
                    $this->exts->type_key_by_xdotool('BackSpace');
                    sleep(1);
                }
                $this->exts->type_text_by_xdotool($this->username);
                sleep(1);

                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector, $this->password);
                sleep(1);

                $this->exts->capture("2-login-page-filled");
                $this->exts->moveToElementAndClick($this->submit_login_selector);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }

    private function checkFillTwoFactor()
    {
        sleep(2);
        $two_factor_selector = 'form[name="sendTokenForm"] input[name="otp"],form[name="sendTokenForm"] input[inputmode="numeric"], input[name="otp-input"]';
        $two_factor_message_selector = 'form[name="sendTokenForm"] p, p[class*="MuiTypography-body"]';
        $two_factor_submit_selector = 'form[name="sendTokenForm"] button[type="submit"]';
        $two_factor_resend_selector = '';

        if ($this->exts->getElement($two_factor_selector) != null) {
            $this->exts->log("Two factor page found.");
            $this->exts->capture("2.1-two-factor");

            $this->exts->two_factor_notif_msg_en = trim($this->exts->extract($two_factor_message_selector));
            $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
            $this->exts->log("Message:\n" . $this->exts->two_factor_notif_msg_en);
            if ($this->exts->two_factor_attempts == 2) {
                $this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en . ' ' . $this->exts->two_factor_notif_msg_retry_en;
                $this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de . ' ' . $this->exts->two_factor_notif_msg_retry_de;
            }
            $this->exts->notification_uid = "";
            $two_factor_code = trim($this->exts->fetchTwoFactorCode());
            if (!empty($two_factor_code) && trim($two_factor_code) != '') {
                $this->exts->log("checkFillTwoFactor: Entering two_factor_code." . $two_factor_code);
                // $resultCodes = str_split($two_factor_code);
                // $code_inputs = $this->exts->getElements($two_factor_selector);
                // foreach ($code_inputs as $key => $code_input) {
                //     if(array_key_exists($key, $resultCodes)){
                //         $this->exts->log('"checkFillTwoFactor: Entering key '. $resultCodes[$key] . 'to input #');
                //         $code_input->sendKeys($resultCodes[$key]);
                //     } else {
                //         $this->exts->log('"checkFillTwoFactor: Have no char for input #');
                //     }
                // }
                //$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
                $this->exts->click_by_xdotool($two_factor_selector);
                sleep(1);
                $this->exts->type_text_by_xdotool($two_factor_code);
                sleep(1);
                if ($this->exts->exists($two_factor_resend_selector)) {
                    $this->exts->moveToElementAndClick($two_factor_resend_selector);
                    sleep(1);
                }
                $this->exts->capture("2.2-two-factor-filled-" . $this->exts->two_factor_attempts);

                $this->exts->moveToElementAndClick($two_factor_submit_selector);
                sleep(15);

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

    public function invoicePage()
    {
        $this->exts->log("Invoice page");

        $current_url = $this->exts->getUrl();
        //download invoices from common page
        $this->exts->openUrl($this->common_invoice_url);
        sleep(20);
        // $this->donwloadInvoices2(1,0);
        $this->processHistoryReceipts();

        $this->exts->openUrl($current_url);
        sleep(9);
        //download invoices from admn specific page
        if (!$this->exts->urlContains('/billing')) {
            $this->exts->moveToElementAndClick('a#mainmenu-link-billing');
            sleep(20);
        }
        $this->downloadInvoice();
        $this->exts->openUrl('https://customer.easypark.net/history/de');
        sleep(20);
        $this->processHistoryReceipts();
    }

    public $totalFiles = 0;
    public function downloadInvoice($count = 1, $pageCount = 1)
    {
        $this->exts->log("Begin download invoice");

        $this->exts->capture('4-List-invoice');

        try {
            if ($this->exts->getElement('table#payment-table tbody tr') != null) {
                $invoices = [];
                $rows = count($this->exts->getElements('table#payment-table tbody tr'));
                for ($i = 0; $i < $rows; $i++) {
                    $row = $this->exts->getElements('table#payment-table tbody tr')[$i];
                    $tags = $this->exts->getElements('td', $row);
                    if (count($tags) >= 9 && $this->exts->getElement('a', $tags[7]) != null) {
                        $this->isNoInvoice = false;
                        // $download_button = $this->exts->getElement('a', $tags[8]);
                        $invoiceName = trim($tags[1]->getAttribute('innerText'));
                        $invoiceFileName = !empty($invoiceName) ? $invoiceName . '.pdf' : '';
                        $invoiceDate = trim($tags[1]->getAttribute('innerText'));
                        $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[4]->getAttribute('innerText'))) . ' EUR';

                        $this->exts->log('--------------------------');
                        $this->exts->log('invoiceName: ' . $invoiceName);
                        $this->exts->log('invoiceDate: ' . $invoiceDate);
                        $this->exts->log('invoiceAmount: ' . $invoiceAmount);
                        $parsed_date = $this->exts->parse_date($invoiceDate, 'Y-m-d', 'Y-m-d');
                        $this->exts->log('Date parsed: ' . $parsed_date);

                        // Download invoice if it not exisited
                        if ($this->exts->invoice_exists($invoiceName)) {
                            $this->exts->log('Invoice existed ' . $invoiceFileName);
                        } else {
                            $download_buttons = $this->exts->getElements('a', $tags[7]);
                            $this->exts->log('Finding Completted trips button...');
                            foreach ($download_buttons as $key => $download_button) {
                                $tab_name = trim($download_button->getAttribute('innerText'));
                                $this->exts->log($tab_name);
                                if (stripos(strtolower($tab_name), 'pdf') !== false) {
                                    $this->exts->log('Completted trips button found');
                                    $download_button->click();
                                    sleep(5);
                                    break;
                                }
                            }
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
        } catch (\Exception $exception) {
            $this->exts->log("Exception downloading invoice " . $exception->getMessage());
        }
    }

    public function processHistoryReceipts()
    {
        if ($this->exts->exists('div.no-parking-desc')) {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->baseUrl);
            sleep(15);
            $this->exts->moveToElementAndClick('ul.NonloggedMenu li#nav-signin a');
            sleep(12);
            $this->checkFillLogin();
            sleep(20);

            $this->exts->openUrl($this->common_invoice_url);
            sleep(20);
        }

        if ($this->restrictPages == 0) {
            while ($this->exts->exists('div.expandAll')) {
                $this->exts->moveToElementAndClick('div.expandAll');
                sleep(5);
            }
        }

        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('div.reportExpand .parking');

        foreach ($rows as $row) {
            if ($this->exts->exists('.pointerItem.toggler', $row)) {
                $expand_button = $this->exts->getElement('.pointerItem.toggler', $row);
                try {
                    $this->exts->log('Click download button');
                    $expand_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$expand_button]);
                }
                sleep(5);
            }

            $invoiceUrl = $this->exts->getElement('a[href*="downloadPdf"]', $row);
            if ($invoiceUrl != null) {
                $invoiceLink = $invoiceUrl->getAttribute("href");
                $invoiceName = explode(
                    '/pdf',
                    array_pop(explode('url=parkings/', $invoiceLink))
                )[0];
                $invoiceDate = trim($this->exts->extract('.parkingZone h2', $row, 'innerText'));
                $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.parking-cost', $row, 'innerText'))) . ' EUR';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl
                ));
                $this->isNoInvoice = false;
            }
        }

        // foreach ($rows as $row) {
        // 	if($this->exts->getElement('a[id*="receiptLink"]', $row) != null) {
        // 		$invoiceUrl = $this->exts->getElement('a[id*="receiptLink"]', $row)->getAttribute("href");
        // 		$invoiceName = trim(explode('-', $this->exts->extract('a[id*="receiptLink"]', $row, 'id'))[0]);
        // 		$invoiceDate = trim($this->exts->extract('.parkingZone h2', $row, 'innerText'));
        // 		$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.parking-cost', $row, 'innerText'))) . ' EUR';

        // 		array_push($invoices, array(
        // 			'invoiceName'=>$invoiceName,
        // 			'invoiceDate'=>$invoiceDate,
        // 			'invoiceAmount'=>$invoiceAmount,
        // 			'invoiceUrl'=>$invoiceUrl
        // 		));
        // 		$this->isNoInvoice = false;
        // 	}
        // }

        // Download all invoices
        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ? $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'Y-m-d', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
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

    public function donwloadInvoices2($count, $startngDivIndex)
    {
        $recordRow = 0;
        $div_selector = '.reportExpand .parking';
        $maxBackDate = date('Y-m-d', strtotime('-3 months'));
        if ($this->restrictPages == 0) {
            $maxBackDate = date('Y-m-d', strtotime('-2 year'));
        }


        if ($this->exts->exists($div_selector)) {

            $div_selector = $this->exts->getElements($div_selector);
            $numOfRecordsInCurentPage = count($div_selector);
            foreach ($div_selector as $key => $div) {


                if ($key < $startngDivIndex) {
                    continue;
                }
                $invoice_date = $this->exts->extract('.nospace .parkTime', $div);
                $invoice_amount = $this->exts->extract('.nospace .parkarea', $div);
                if ($invoice_amount != null) {
                    $invoice_amount = $this->cleanInvoiceAmount($invoice_amount);
                }


                if ($invoice_date < $maxBackDate) {
                    return;
                }

                //click on row expand icon
                // $row_expand_icon = $this->exts->getElement('.fa-chevron-left',$div);
                // $row_expand_icon->click();

                $icon_index = $key + 1;
                $row_expand_icon_selector = '.reportExpand .parking:nth-child(' . $icon_index . ') .fa-chevron-left';
                $this->exts->moveToElementAndClick($row_expand_icon_selector);

                sleep(5);

                $invoice_url_tag_selector =  'a[id*="receiptLink"]';
                $receipt = $this->exts->getElement($invoice_url_tag_selector, $div);
                $receipt_link = '';
                if ($receipt != null) {
                    $receipt_link =  $receipt->getAttribute('href');
                }

                $filename = explode("=", $receipt_link)[2];
                $invoice_name = explode(".", $filename)[0];

                $this->exts->log('___ processing record___');
                $this->exts->log('invoice date: ' . $invoice_date);
                $this->exts->log('invoice_amount: ' . $invoice_amount);
                $this->exts->log('invoice_name: ' . $invoice_name);
                $this->exts->log('filename : ' . $filename);
                $this->exts->log('invoice url : ' . $receipt_link);

                try {
                    $downloaded_file = $this->exts->direct_download($receipt_link, "pdf", $filename);
                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $pdf_content = file_get_contents($downloaded_file);
                        if (stripos($pdf_content, "%PDF") !== false) {
                            $this->exts->new_invoice($invoice_name, $invoice_date, $invoice_amount, $downloaded_file);
                            $this->isNoInvoice = false;
                        } else {
                            $this->exts->log("donwloadInvoices2 :: Not Valid PDF - " . $filename);
                        }
                    } else {
                        $this->exts->log("donwloadInvoices2 :: No File Downloaded ? - " . $downloaded_file);
                    }
                } catch (\Exception $exception) {
                    $this->exts->log("donwloadInvoices2::Exception  " . $exception->getMessage());
                }
            }
        }


        $more_selector = '.expandAll';
        if ($this->exts->exists($more_selector)) {

            $count++;
            $this->exts->moveToElementAndClick($more_selector);
            sleep(10);
            $this->donwloadInvoices2($count, $numOfRecordsInCurentPage);
        }
    }

    public function cleanInvoiceAmount($value)
    {
        try {
            return preg_replace('/[^0-9-.,]+/', '', $value);
        } catch (\Exception $exception) {
            $this->exts->log("Exception exists - " . $exception->getMessage());
            return false;
        }
    }
}

exec("docker rm -f selenium-node-111");
exec("docker run -d --shm-size 2g -p 5902:5900 -p 9990:9999 -e TZ=Europe/Berlin -e SE_NODE_SESSION_TIMEOUT=86400 -e LANG=de -e GRID_TIMEOUT=0 -e GRID_BROWSER_TIMEOUT=0 -e SCREEN_WIDTH=1920 -e SCREEN_HEIGHT=1080 --name selenium-node-111 -v /var/www/remote-chrome/downloads:/home/seluser/Downloads/111 remote-chrome:v1");

$browserSelected = 'chrome';
$portal = new PortalScriptCDP($browserSelected, 'test_remote_chrome', '111', 'office@sayaq-adventures-muenchen.com', 'Sayaq#2022');
$portal->run();
