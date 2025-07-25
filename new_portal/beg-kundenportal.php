<?php

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

    /*Define constants used in script*/
    public $baseUrl = 'https://kundenportal-beg.de/login?redirectUrl=%2Forders';
    public $loginUrl = 'https://kundenportal-beg.de/login?redirectUrl=%2Forders';
    public $invoicePageUrl = 'https://kundenportal-beg.de/login?redirectUrl=%2Forders';

    public $username_selector = 'input[formcontrolname="username"]';
    public $password_selector = 'input[formcontrolname="password"]';
    public $remember_me_selector = '';
    public $submit_login_selector = 'span[id="coma-submit-button-login-label"]';

    public $check_login_failed_selector = 'mat-label.coma-error-text.ng-star-inserted';
    public $check_login_success_selector = 'mat-icon[id="coma-header-user-profile-icon"]';

    public $isNoInvoice = true;
    public $restrictPages = 3;

    /**
     * Entry Method thats called for a portal
     * @param Integer $count Number of times portal is retried.
     */
    private function initPortal($count)
    {
        $this->exts->log('Begin initPortal ' . $count);
        $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

        $this->exts->log('restrictPages:: ' .  $this->restrictPages);

        $this->exts->openUrl($this->baseUrl);
        sleep(10);

        $this->exts->loadCookiesFromFile();
        sleep(1);
        if (!$this->checkLogin()) {
            $this->exts->log('NOT logged via cookie');

            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            $this->fillForm(0);
        }
        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");

            $this->dateRange();

            // Final, check no invoice
            if ($this->isNoInvoice) {
                $this->exts->no_invoice();
            }

            $this->exts->success();
        } else {
            $this->exts->log(__FUNCTION__ . '::Use login failed');
            $this->exts->log(__FUNCTION__ . '::Last URL: ' . $this->exts->getUrl());

            $error_text = strtolower($this->exts->extract($this->check_login_failed_selector));

            $this->exts->log(__FUNCTION__ . '::Error text: ' . $error_text);
            if (stripos($error_text, strtolower('Anmeldedaten ungültig. Bitte überprüfen Sie Ihre Eingabe')) !== false) {
                $this->exts->loginFailure(1);
            } else {
                $this->exts->loginFailure();
            }
        }
    }

    public function fillForm($count)
    {
        $this->exts->log("Begin fillForm " . $count);

        $this->exts->waitTillPresent($this->username_selector);
        if ($this->exts->querySelector($this->username_selector) != null) {

            $this->exts->capture("1-pre-login");
            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector, $this->username);
            sleep(2);
            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector, $this->password);
            sleep(2);

            if ($this->exts->exists($this->remember_me_selector)) {
                $this->exts->click_by_xdotool($this->remember_me_selector);
                sleep(2);
            }

            $this->exts->capture("1-login-page-filled");
            if ($this->exts->exists($this->submit_login_selector)) {
                $this->exts->moveToElementAndClick($this->submit_login_selector);
                sleep(2);
            }
        } else {
            $this->exts->log(__FUNCTION__ . '::Login page not found');
            $this->exts->capture("2-login-page-not-found");
        }
    }
    /**
     * Method to Check where user is logged in or not
     * return boolean true/false
     */
    public  function checkLogin()
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

    public function dateRange()
    {

        if ($this->exts->querySelector('mat-datepicker-toggle button') != null) {
            $this->exts->moveToElementAndClick('mat-datepicker-toggle button');
            sleep(5);
        }


        $selectDate = new DateTime();
        $currentDate = strtoupper($selectDate->format('F Y'));

        if ($this->restrictPages == 0) {
            $selectDate->modify('-3 years');
            $formattedDate = strtoupper($selectDate->format('F Y'));
            $this->exts->capture('date-range-3-years');
        } else {
            $selectDate->modify('-3 months');
            $formattedDate = strtoupper($selectDate->format('F Y'));
            $this->exts->capture('date-range-3-months');
        }


        $stop = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('button.mat-calendar-period-button span.mdc-button__label span');
            $this->exts->log('previous currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('previous formattedDate:: ' . trim($formattedDate));

            if (trim($calendarMonth) === trim($formattedDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('button.mat-calendar-previous-button');
            sleep(1);
            $stop++;

            if ($stop > 200) {
                break;
            }
        }

        $this->exts->moveToElementAndClick('tbody.mat-calendar-body tr:nth-child(2) td:nth-child(1) button');
        sleep(5);

        // select To date

        $stop2  = 0;
        while (true) {
            $calendarMonth = $this->exts->extract('button.mat-calendar-period-button span.mdc-button__label span');
            $this->exts->log('next currentMonth:: ' . trim($calendarMonth));
            $this->exts->log('next currentDate:: ' . trim($currentDate));

            if (trim($calendarMonth) === trim($currentDate)) {
                sleep(4);
                break;
            }

            $this->exts->moveToElementAndClick('button.mat-calendar-next-button');
            sleep(1);

            $stop2++;
            if ($stop2 > 200) {
                break;
            }
        }

        $this->exts->moveToElementAndClick('span.mat-calendar-body-today');
        sleep(5);

        $this->downloadInvoices();
    }

    private function downloadInvoices($count = 1)
    {
        $this->exts->log(__FUNCTION__);

        $this->exts->waitTillPresent('coma-order-card');
        $this->exts->capture("4-invoices-classic");

        $invoices = [];
        $rows = $this->exts->getElements('coma-order-card');
        foreach ($rows as $key => $row) {
            $invoiceLink = $this->exts->getElement('a[href*="/coma/documents/download/"]', $row);
            if ($invoiceLink != null) {
                $invoiceUrl = $invoiceLink->getAttribute("href");
                $invoiceName = $this->exts->extract('div.order-supplier-info1 > coma-order-info:nth-child(1)  p.order-info-text', $row);
                $invoiceDate = $this->exts->extract('div.order-supplier-info1 > coma-order-info:nth-child(2)  p.order-info-text', $row);
                $invoiceAmount = '';

                array_push($invoices, array(
                    'invoiceName' => $invoiceName,
                    'invoiceDate' => $invoiceDate,
                    'invoiceAmount' => $invoiceAmount,
                    'invoiceUrl' => $invoiceUrl,
                ));
                $this->isNoInvoice = false;
            }
        }

        $this->exts->log('Invoices found: ' . count($invoices));
        foreach ($invoices as $invoice) {
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
            $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
            $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
            $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

            $invoiceFileName = !empty($invoice['invoiceName']) ?  $invoice['invoiceName'] . '.pdf' : '';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);

            $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }

        $paginations = $this->exts->getElements('mat-paginator button.mat-mdc-paginator-navigation-next');
        $isDisabled = $this->exts->getElements('mat-paginator button.mat-mdc-paginator-navigation-next.mat-mdc-tooltip-disabled');

        if (count($paginations) != 0 && count($isDisabled) == 0) {
            try {
                $paginations[0]->click();
            } catch (\Exception $e) {
                $this->exts->execute_javascript("arguments[0].click();", [$paginations[0]]);
            }
            $this->downloadInvoices();
        }
    }
}
