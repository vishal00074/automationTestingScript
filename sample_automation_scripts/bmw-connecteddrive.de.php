<?php // migrated
// Server-Portal-ID: 9773 - Last modified: 27.09.2024 14:36:40 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.bmw-connecteddrive.de/app/index.html';
public $loginUrl = '';
public $invoicePageUrl = 'https://www.bmw.de/de/shop/ls/orders/connected-drive';

public $username_selector = 'input#email';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = '#login-form button.primary, button.confirm-btn';

public $check_login_failed_selector = 'form.form-signin[name="profileForm"] .error, div.notification-message.error';
public $check_login_success_selector = '.site-header-desktop-nav-wrapper span[inline-icon="logout"], a.myBmwLogout, [class*="mybmw-logged-in"]';
public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    // Load cookies
    $this->exts->loadCookiesFromFile();
    sleep(1);
    $this->exts->openUrl($this->baseUrl);
    sleep(10);
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->baseUrl);
        sleep(15);
        if ($this->exts->exists('button.react-responsive-modal-closeButton')) {
            $this->exts->moveToElementAndClick('button.react-responsive-modal-closeButton');
        }
        sleep(5);
        $this->exts->moveToElementAndClick('[data-button-id="myBmw"]');
        sleep(3);
        $this->exts->moveToElementAndClick('#flyout-login');
        sleep(15);
        $this->checkFillLogin();
        sleep(7);
    }

    if ($this->exts->getElement('.user-policy-confirmation-popup-checkbox > label.input-checkbox-icon') != null) {
        $this->exts->moveToElementAndClick('.user-policy-confirmation-popup-checkbox > label.input-checkbox-icon');
        $this->exts->moveToElementAndClick('user-policy-confirmation-popup button[ng-click="vm.onAcceptButtonClicked()"]');
        sleep(10);
    }

    if ($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        $this->exts->openUrl($this->invoicePageUrl);
        sleep(5);
        if ($this->exts->exists('button.react-responsive-modal-closeButton')) {
            $this->exts->moveToElementAndClick('button.react-responsive-modal-closeButton');
        }
        $this->processInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'falschen anmeldedaten eingegeben') !== false || stripos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'wrong credentials') !== false) {
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
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(2);

        $this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(5);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(2);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(5);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);

        if ($this->exts->getElement('div.modal-close') != null) {
            $this->exts->moveToElementAndClick('div.modal-close');
            sleep(5);
        }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function processInvoices()
{
    sleep(25);

    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->getElements('li.orderable-list-entry');
    foreach ($rows as $index => $row) {
        $row = $this->exts->getElements('li.orderable-list-entry')[$index];

        $tags = $this->exts->getElements('orderable-list-entry', $row);
        if (count($tags) >= 5 && $this->exts->getElement('a', $tags[4]) != null) {
            $viewDetialSelector = $this->exts->getElement('a', $tags[4]);
            $viewDetialSelector->click();
            sleep(10);

            $detailRows = $this->exts->getElements('[data-entries="vm.invoices"] li.orderable-list-entry');
            foreach ($detailRows as $key => $detailRow) {

                $detailRowsTags = $this->exts->getElements('orderable-list-entry', $detailRow);

                $invoiceSelector = $this->exts->getElement('.order-details-download', $detailRowsTags[2]);
                $tdContent = explode("(", trim($detailRowsTags[2]->getText()))[1];
                $invoiceName = trim(explode(")", $tdContent)[0]);

                $invoiceDate = trim($detailRowsTags[0]->getText());
                $this->exts->log('Date before parsed: ' . $invoiceDate);
                $invoiceDate = $this->exts->parse_date($invoiceDate, 'd.m.Y', 'Y-m-d');
                $invoiceAmount = '';


                $this->exts->log('--------------------------');
                $this->exts->log('invoiceName: ' . $invoiceName);
                $this->exts->log('invoiceDate: ' . $invoiceDate);
                $this->exts->log('invoiceAmount: ' . $invoiceAmount);

                $invoiceFileName = $invoiceName . '.pdf';
                // Download invoice if it not exisited
                if ($this->exts->invoice_exists($invoiceName)) {
                    $this->exts->log('Invoice existed ' . $invoiceFileName);
                } else {
                    $invoiceSelector->click();
                    $this->exts->wait_and_check_download('pdf');
                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);
                    if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                        sleep(1);
                        $this->isNoInvoice = false;
                    } else {
                        $this->exts->log('Timeout when download ' . $invoiceFileName);
                    }
                }
            }

            //go back to order page
            $this->exts->moveToElementAndClick('[ui-sref="orderListPage"]');
        }
    }

    $invoices = [];

    $rows = $this->exts->getElements('div[data-testid="orders-grid"]');
    foreach ($rows as $row) {
        if ($this->exts->getElement('div[data-testid*="order-details"] a[href*="/orders"]', $row) != null) {
            $invoiceUrl = $this->exts->getElement('div[data-testid*="order-details"] a[href*="/orders"]', $row)->getAttribute("href");
            array_push($invoices, array(
                'invoiceUrl' => $invoiceUrl
            ));
            $this->isNoInvoice = false;
        }
    }

    // Download all invoices
    $this->exts->log('Invoices found: ' . count($invoices));
    foreach ($invoices as $invoice) {
        $this->exts->openUrl($invoice['invoiceUrl']);
        sleep(9);

        $invoiceName = trim($this->exts->extract('div[data-testid="order-number"] div[data-testid="category-value"]', null, 'innerText'));
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoiceName);

        $invoiceFileName = $invoiceName . '.pdf';
        $invoiceUrl = trim($this->exts->extract('a[href*="/orders/documents/"]', null, 'href'));
        if ($invoiceUrl != '') {
            $downloaded_file = $this->exts->direct_download($invoiceUrl, 'pdf', $invoiceFileName);
            if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                $this->exts->new_invoice($invoiceName, '', '', $invoiceFileName);
                sleep(1);
            } else {
                $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
            }
        }
    }
}