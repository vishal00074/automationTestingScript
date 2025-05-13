<?php // migrated and updated Login code and download code
// Server-Portal-ID: 9445 - Last modified: 27.01.2025 13:00:51 UTC - User: 1

public $baseUrl = "https://www.t-mobile.at/";
public $loginUrl = "https://www.t-mobile.at/";
public $homePageUrl = "https://www.t-mobile.at/";
public $username_selector = 'form#bn_loginform input#msisdn_txt';
public $password_selector = 'form#bn_loginform input#password_txt';
public $submit_button_selector = 'form#bn_loginform input.magentaBtn[type="submit"]';
public $username_selector1 = 'form[name="login"] input[name="account"], input[name="username"]';
public $password_selector1 = 'form[name="login"] input[name="password"], input[name="password"]';
public $submit_button_selector1 = 'form[name="login"] button[type="submit"], button[name="login"]';
public $username_selector2 = 'form.magenta-form input[name="j_username"], input[name="username"]';
public $password_selector2 = 'form.magenta-form input[name="j_password"], input[name="password"]';
public $submit_button_selector2 = 'form.magenta-form input[type="submit"], button[name="login"]';
public $login_tryout = 0;
public $restrictPages = 3;

public $first_loggedin = true;
public $totalFiles = 0;

private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);

    $this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

    $this->exts->openUrl($this->baseUrl);
    sleep(5);
    if ($this->exts->exists('#uc-btn-accept-banner')) {
        // accept cookie
        $this->exts->moveToElementAndClick('#uc-btn-accept-banner');
        sleep(3);
    }

    $this->accept_cookies();

   
    $this->exts->execute_javascript('let btn = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=\"uc-accept-all-button\"]");
    if(btn){btn.click();}');

    sleep(3);
    $this->exts->capture("Home-page-without-cookie");

    $isCookieLoginSuccess = false;
    if ($this->exts->loadCookiesFromFile()) {
        $this->exts->openUrl($this->homePageUrl);
        sleep(15);
        $this->exts->execute_javascript('let btn = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=\"uc-accept-all-button\"]");
    if(btn){btn.click();}');
        sleep(3);
        if ($this->exts->exists('#uc-btn-accept-banner')) {
            // accept cookie
            $this->exts->moveToElementAndClick('#uc-btn-accept-banner');
            sleep(3);
        }

        if ($this->checkLogin()) {
            $isCookieLoginSuccess = true;
        } else {
            $this->exts->clearCookies();
            $this->exts->openUrl($this->loginUrl);
            sleep(10);
        }
    }
    sleep(5);
    $this->exts->execute_javascript('let btn = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=\"uc-accept-all-button\"]");
    if(btn){btn.click();}');
    sleep(3);

    if ($this->exts->exists('#uc-btn-accept-banner')) {
        // accept cookie
        $this->exts->moveToElementAndClick('#uc-btn-accept-banner');
        sleep(3);
    }

    if (!$isCookieLoginSuccess) {
        $this->exts->capture("after-login-clicked");

        $this->fillForm(0);
        sleep(10);

        if ($this->checkLogin()) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $this->exts->capture("LoginSuccess");
            $this->invoicePage();
        } else {
            $this->exts->openUrl('https://mein.t-mobile.at/myTNT/portlet.page?shortcut=ebill');
            sleep(15);
            $this->exts->capture('third-login-method');

            sleep(5);
            $this->exts->execute_javascript('let btn = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=\"uc-accept-all-button\"]");
    if(btn){btn.click();}');
            sleep(3);

            if ($this->exts->exists('#uc-btn-accept-banner')) {
                // accept cookie
                $this->exts->moveToElementAndClick('#uc-btn-accept-banner');
                sleep(3);
            }

            if ($this->exts->exists($this->username_selector2) || $this->exts->exists($this->password_selector2)) {
                sleep(2);
                $this->login_tryout = (int)$this->login_tryout + 1;
                $this->exts->capture("1-pre-login-3");

                $this->exts->log("Enter Username");
                $this->exts->moveToElementAndType($this->username_selector2, $this->username);
                sleep(1);
                $this->exts->log("Enter Password");
                $this->exts->moveToElementAndType($this->password_selector2, $this->password);
                sleep(1);
                $this->exts->capture("1-pre-login-filled-3");

                $this->exts->moveToElementAndClick($this->submit_button_selector2);
                sleep(15);

                $err_msg = $this->exts->extract('p.error');
                $this->exts->log($err_msg);
                $this->exts->log('============= is first loggin atttempt: ' . ($this->first_loggedin ? 'true' : 'false'));

                if ($err_msg != '' && $this->first_loggedin == false) {
                    $this->exts->log($err_msg);
                    $this->exts->loginFailure(1);
                }
                $this->exts->capture("1-pre-login-submitted-3");
            }
            sleep(10);
            if ($this->exts->exists('button[ng-unit-test-locator="submitall-consents-button"]')) {
                $this->exts->moveToElementAndClick('button[ng-unit-test-locator="submitall-consents-button"]');
            }

            $invUrl = $this->exts->extract('iframe#frmMain', null, 'src');
            if ($invUrl != '') {
                $this->exts->openUrl($invUrl);
                sleep(15);

                $this->exts->moveToElementAndClick('div.section-invoices div[onclick*="getBillData"]');

                $this->downloadInvoice1();
            } elseif ($this->exts->exists('a[aria-label="Rechnungen & Zahlungen"]')) {
                $this->exts->moveToElementAndClick('a[aria-label="Rechnungen & Zahlungen"]');
                sleep(15);
                $this->downloadInvoice2();
            } else {
                $this->exts->capture("LoginFailed");
                $this->exts->loginFailure();
            }

            if ($this->totalFiles == 0) {
                $this->exts->log("No invoice !!! ");
                $this->exts->no_invoice();
            }
        }
    } else {
        sleep(10);
        $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
        $this->exts->capture("LoginSuccess");
        $this->invoicePage();
    }
}

private function accept_cookies()
{
     $this->exts->log("Accecpt Cookies");
     $this->exts->execute_javascript('let shadowHost = document.querySelector("#usercentrics-cmp-ui");
        if (shadowHost && shadowHost.shadowRoot) {
            let footer = shadowHost.shadowRoot.querySelector("footer.center");
            if (footer) {
                let acceptButton = footer.querySelector("button.accept.uc-accept-button");
                if (acceptButton) acceptButton.click();
            }
        }
    ');

}

function fillForm($count)
{
    $this->exts->log("Begin fillForm " . $count);
   
    sleep(5);
    if ($this->exts->exists($this->username_selector) || $this->exts->exists($this->password_selector)) {
        sleep(2);
        $this->login_tryout = (int)$this->login_tryout + 1;
        $this->exts->capture("1-pre-login-1");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);
        // TODO: check and put this in the correct line of code flow
        // if (preg_replace('/[.\/0-9]{3,24}/', '', $this->username) != '') {
        // 	$this->exts->log('Username was wrong phone number format');
        // 	$this->exts->loginFailure(1);
        // }
        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(4);
        $this->exts->capture("1-pre-login-filled-1");

        $this->exts->moveToElementAndClick($this->submit_button_selector);
        sleep(10);

        $err_msg = $this->exts->extract('div.login-error');

        if ($err_msg != "" && $err_msg != null) {
            $this->exts->log($err_msg);
            $this->exts->loginFailure(1);
        }
        sleep(8);
        $this->exts->capture("1-pre-login-submitted-1");
    } else {
        $this->exts->openUrl('https://rechnung.magenta.at/index.cfm');
        sleep(10);

        if ($this->exts->exists($this->username_selector1) || $this->exts->exists($this->password_selector1)) {
            sleep(2);
            $this->login_tryout = (int)$this->login_tryout + 1;
            $this->exts->capture("1-pre-login-2");

            $this->exts->log("Enter Username");
            $this->exts->moveToElementAndType($this->username_selector1, $this->username);
            sleep(1);

            $this->exts->log("Enter Password");
            $this->exts->moveToElementAndType($this->password_selector1, $this->password);
            sleep(1);
            $this->exts->capture("1-pre-login-filled-2");

            $this->exts->moveToElementAndClick($this->submit_button_selector1);
            sleep(15);

            // try {
            //     $error_message = $this->exts->webdriver->switchTo()->alert()->getText();
            //     $this->first_loggedin = false;
            //     $this->exts->log('========== first_loggedin: ' . $error_message);
            //     $this->exts->webdriver->switchTo()->alert()->accept();
            //     $this->exts->webdriver->switchTo()->defaultContent();
            // } catch (\Exception $exception) {
            //     $this->exts->log(__FUNCTION__ . ' : exception closing alert' . $exception->getMessage());
            // }

            $err_msg = $this->exts->extract('div#left_content_area b');
            if (strpos(strtolower($err_msg), 'fehler') !== false) {
                $this->first_loggedin = false;
                $this->exts->log('========== first_loggedin: ' . $err_msg);
            }
            sleep(8);
            $this->exts->capture("1-pre-login-submitted-2");
        }
    }

    sleep(10);
   
}

function checkLogin()
{
    $this->exts->log("Begin checkLogin ");
    $isLoggedIn = false;
    
    if ($this->exts->getElement('a#bn_logout[style*="display: block"]') != null || $this->exts->getElement('[name="bill_id"] option') != null) {
        $this->exts->log(">>>>>>>>>>>>>>>Login successful1!!!!");
        $isLoggedIn = true;

    } else {
        $err_msg = $this->exts->extract('div#left_content_area b');
        $this->exts->log($err_msg);

        if ($err_msg != '' && strpos(strtolower($err_msg), 'fehler') === false) {
            $this->exts->log(">>>>>>>>>>>>>>>Login successful!!!!");
            $isLoggedIn = true;
        }
    }
    
    return $isLoggedIn;
}

function invoicePage()
{
    $this->exts->log("Invoice page");
    $this->downloadInvoice();

    $this->exts->openUrl('https://mein.t-mobile.at/myTNT/portlet.page?shortcut=ebill');
    
    $this->exts->waitTillPresent('button#uc-btn-accept-banner');

    $this->exts->moveToElementAndClick('button#uc-btn-accept-banner');
    $this->exts->execute_javascript('let btn = document.querySelector("#usercentrics-root").shadowRoot.querySelector("button[data-testid=\"uc-accept-all-button\"]");
    if(btn){btn.click();}');
    sleep(3);
    sleep(3);

    if ($this->exts->exists($this->username_selector2) || $this->exts->exists($this->password_selector2)) {
        sleep(2);
        $this->login_tryout = (int)$this->login_tryout + 1;
        $this->exts->capture("1-pre-login");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector2, $this->username);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector2, $this->password);

        $this->exts->moveToElementAndClick($this->submit_button_selector2);
        sleep(15);

        $err_msg = $this->exts->extract('p.error');
        $this->exts->log($err_msg);
        $this->exts->log($this->first_loggedin);

        // if ($err_msg != '' && $this->first_loggedin == false) {
        // 	$this->exts->log($err_msg);
        // 	$this->exts->loginFailure(1);
        // }
    }

    $invUrl = $this->exts->extract('iframe#frmMain', null, 'src');
    if ($invUrl != '') {
        $this->exts->openUrl($invUrl);
        sleep(15);

        $this->exts->moveToElementAndClick('div.section-invoices div[onclick*="getBillData"]');

        $this->downloadInvoice1();
    }

    if ($this->totalFiles == 0) {
        $this->exts->log("No invoice !!! ");
        $this->exts->no_invoice();
    }

    $this->exts->success();
}

function downloadInvoice()
{
    $this->exts->log("Begin download invoice");

    $this->exts->capture('4-List-invoice');

    try {
        if ($this->exts->getElement('[name="bill_id"] option') != null) {
            $receipts = $this->exts->getElements('[name="bill_id"] option');
            $invoices = array();
            foreach ($receipts as $i => $receipt) {
                $receiptDate = $receipt->getAttribute('innerText');
                $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                $valueDate = $receipt->getAttribute('value');
                $invoice = array(
                    'receiptDate' => '',
                    'receiptName' => '',
                    'receiptUrl' => '',
                    'parsed_date' => $parsed_date,
                    'receiptAmount' => '',
                    'receiptFileName' => '',
                    'valueDate' => $valueDate

                );
                array_push($invoices, $invoice);
            }

            $this->exts->log("Invoice found: " . count($invoices));
            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                // $this->exts->changeSelectbox('[name="bill_id"]', $invoice['valueDate']);

                $this->exts->execute_javascript('
                    let selectBox = document.querySelector("[name=\'bill_id\']");
                    selectBox.value = "' . $invoice['valueDate'] . '";
                    selectBox.dispatchEvent(new Event("change", { bubbles: true }));
                ');

                sleep(2);

                $this->exts->moveToElementAndClick('[onclick="document.select_bill.submit()"]');
                sleep(15);

                $receiptNameEl = $this->exts->getElement('//*[contains(text(), "Rechnungsnummer")]/../following-sibling::td[1]', null, 'xpath');
                if ($receiptNameEl != null) {
                    $invoice['receiptName'] = trim($receiptNameEl->getAttribute('innerText'));
                } else {
                    $invoice['receiptName'] = str_replace('.', '', $invoice['receiptDate']);
                }

                $invoice['receiptFileName'] = $invoice['receiptName'] . '.pdf';

                $this->exts->log("Invoice Date: " . $invoice['receiptDate']);
                $this->exts->log("Invoice Name: " . $invoice['receiptName']);
                $this->exts->log("Invoice FileName: " . $invoice['receiptFileName']);
                $this->exts->log("Invoice parsed_date: " . $invoice['parsed_date']);
                $this->exts->log("Invoice Amount: " . $invoice['receiptAmount']);

                if ($this->exts->exists('select[name="select_download"]')) {
                    $this->exts->moveToElementAndClick('select[name="select_download"]');
                    sleep(2);

                    $this->exts->type_key_by_xdotool('Down');
                    sleep(2);
                    $this->exts->type_key_by_xdotool('Return');
                    sleep(5);
                    for ($i = 0; $i < 5; $i++) {
                        $this->exts->type_key_by_xdotool('Tab');
                        sleep(1);
                    }
                    $this->exts->type_key_by_xdotool('Return');
                    sleep(2);

                    // $this->exts->moveToElementAndClick('select[name="select_download"] option[value*="bill_pdf_download"]:not(#id_signed_bill)');

                    $this->exts->wait_and_check_download('pdf');

                    $downloaded_file = $this->exts->find_saved_file('pdf', $invoice['receiptFileName']);
                    sleep(1);

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->log("create file");
                        $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                    }
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function downloadInvoice1()
{
    $this->exts->log("Begin download invoice 1");

    $this->exts->capture('4-List-invoice-1');

    try {
        if ($this->exts->getElement('div.section-invoices div[onclick*="getBillData"]') != null) {
            $receipts = $this->exts->getElements('div.section-invoices div[onclick*="getBillData"]');
            $invoices = array();
            foreach ($receipts as $receipt) {
                if ($this->exts->getElement('a[href*="fuseaction=bill_pdf_download"]', $receipt) != null) {
                    $receiptDate = $this->exts->extract('div.row.overview table > tbody > tr > td:nth-child(2)', $receipt);
                    $receiptUrl = $this->exts->extract('a[href*="fuseaction=bill_pdf_download"]', $receipt, 'href');
                    $receiptName = trim(explode("'", $receipt->getAttribute('onclick'))[1]);
                    $receiptFileName = $receiptName . '.pdf';
                    $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                    $receiptAmount = $this->exts->extract('span.value-tag', $receipt);
                    $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';

                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice URL: " . $receiptUrl);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'receiptUrl' => $receiptUrl,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName
                    );
                    array_push($invoices, $invoice);
                }
            }

            $this->exts->log("Invoice found: " . count($invoices));

            foreach ($invoices as $invoice) {
                $this->totalFiles += 1;
                $downloaded_file = $this->exts->direct_download($invoice['receiptUrl'], 'pdf', $invoice['receiptFileName']);
                if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['receiptName'], $invoice['parsed_date'], $invoice['receiptAmount'], $downloaded_file);
                }
            }
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}

function downloadInvoice2()
{
    $this->exts->log("Begin download invoice 1");

    $this->exts->capture('4-List-invoice-1');

    try {
        if ($this->exts->getElement('section[class*="ng-paid-invoices"] div[class*="ng-interaction-row"] a') != null) {
            $receipts = $this->exts->getElements('section[class*="ng-paid-invoices"] div[class*="ng-interaction-row"] a');
            $invoicesLink = array();
            foreach ($receipts as $receipt) {
                array_push($invoicesLink, $receipt->getAttribute('href'));
            }
            $this->exts->log("Invoice found: " . count($invoicesLink));
            foreach ($invoicesLink as $invoice) {

                $this->exts->openUrl($invoice);
                sleep(10);
                if ($this->exts->getElement('button[class*="download-btn"]') != null || $this->exts->getElement('a[ng-unit-test-locator="download-documents-link"]')) {
                    $receiptDate = $this->exts->extract('div[ng-unit-test-locator="invoice-details-bill-date"]');
                    $receiptUrl = $invoice;
                    $receiptName = $this->exts->extract('div[ng-unit-test-locator="invoice-further-details-bill-no"]');
                    $receiptFileName = $receiptName . '.pdf';
                    $parsed_date = $this->exts->parse_date($receiptDate, 'd.m.Y', 'Y-m-d');
                    $receiptAmount = $this->exts->extract('div[ng-unit-test-locator="invoice-further-details-amount-due"]');
                    $receiptAmount = trim(preg_replace('/[^\d\.\,]/', '', $receiptAmount)) . ' EUR';

                    $this->exts->log("Invoice Date: " . $receiptDate);
                    $this->exts->log("Invoice URL: " . $receiptUrl);
                    $this->exts->log("Invoice Name: " . $receiptName);
                    $this->exts->log("Invoice FileName: " . $receiptFileName);
                    $this->exts->log("Invoice parsed_date: " . $parsed_date);
                    $this->exts->log("Invoice Amount: " . $receiptAmount);
                    $invoice = array(
                        'receiptName' => $receiptName,
                        'receiptUrl' => $receiptUrl,
                        'parsed_date' => $parsed_date,
                        'receiptAmount' => $receiptAmount,
                        'receiptFileName' => $receiptFileName
                    );
                    $downloadBtnSelector = 'button[class*="download-btn"]';
                    if ($this->exts->exists('a[ng-unit-test-locator="download-documents-link"]')) {
                        $downloadLink = $this->exts->getElement('a[ng-unit-test-locator="download-documents-link"]');
                        $this->exts->openUrl($downloadLink->getAttribute('href'));
                        sleep(10);
                    }
                    // array_push($invoices, $invoice);
                    $downloaded_file = $this->exts->click_and_download($downloadBtnSelector, 'pdf', $receiptFileName, 'CSS', 1);
                    $this->exts->log("Download file: " . $downloaded_file);
                    $this->totalFiles++;

                    if (trim($downloaded_file) != "" && file_exists($downloaded_file)) {
                        $this->exts->new_invoice($receiptFileName, $parsed_date, $receiptAmount, $downloaded_file);
                        sleep(1);
                    } else {
                        $this->exts->log(__FUNCTION__ . '::No download ' . $receiptFileName);
                    }
                }
            }

            // $this->exts->log("Invoice found: " . count($invoices));
        }
    } catch (\Exception $exception) {
        $this->exts->log("Exception downlaoding invoice " . $exception->getMessage());
    }
}