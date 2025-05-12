<?php // migrated 
// Server-Portal-ID: 7906 - Last modified: 07.02.2025 06:16:23 UTC - User: 1

public $baseUrl = 'https://www.wir-machen-druck.de/konto_tracking_list.htm';
public $loginUrl = 'https://www.wir-machen-druck.de/konto_tracking_list.htm';
public $invoicePageUrl = 'https://www.wir-machen-druck.de/konto_tracking_list.htm';

public $username_selector = 'input[name="kundennummer"]';
public $password_selector = 'input#passwort';
public $remember_me_selector = '';
public $submit_login_selector = 'button[name=Submit]';

public $username_alt_selector = '.rightsidebar-section form.login-form input[name="kundennr"]';
public $password_alt_selector = '.rightsidebar-section form.login-form input[name="kundenpasswort"]';
public $submit_login_alt_selector = '.rightsidebar-section form.login-form input[name="kundenholensubmit"]';

public $check_login_failed_selector = 'div[class*=msg-box]';
public $check_login_success_selector = 'input#LogOut, input[name="LogOut"]';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count)
{
    $this->exts->log('Begin initPortal ' . $count);
    $this->disable_uBlock_extensions();
    $this->exts->openUrl($this->baseUrl);


    // Load cookies
    $this->exts->loadCookiesFromFile();
    $this->exts->openUrl($this->baseUrl);

    $this->exts->capture('1-init-page');
    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if ($this->exts->querySelector($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        // if($this->exts->exists('button.uc-btn-accept')) {
        //     $this->exts->moveToElementAndClick('button.uc-btn-accept');
        //     sleep(1);
        // }
        // $this->exts->moveToElementAndClick('i.fa-user');

        $this->exts->execute_javascript('
        var shadow = document.querySelector("#usercentrics-root");
            if(shadow){
                shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
            }
        ');


        $this->checkFillLogin();
    }

    $this->exts->waitTillPresent($this->check_login_success_selector, 20);
    if ($this->exts->querySelector($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__ . '::User logged in');
        $this->exts->capture("3-login-success");

        if ($this->exts->exists('button.uc-btn-accept')) {
            $this->exts->moveToElementAndClick('button.uc-btn-accept');
            sleep(1);
        }

        // Open invoices url and download invoice
        $this->exts->openUrl($this->invoicePageUrl);
        $this->exts->execute_javascript('
            var shadow = document.querySelector("#usercentrics-root");
                if(shadow){
                    shadow.shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\').click();
                }
        ');
        $this->processInvoices();

        // Final, check no invoice
        if ($this->isNoInvoice) {
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__ . '::Use login failed');
        if (strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'text')), 'passwor') !== false) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->getElementByText('li.warning-item', ['Kundendaten gefunden werden', 'no valid customer data'], null, false) != null) {
            $this->exts->loginFailure(1);
        }
        if ($this->exts->urlContains('resetpassword=1')) {
            $this->exts->account_not_ready();
        }
        $this->exts->loginFailure();
    }
}


private function checkFillLogin()
{
    $this->exts->waitTillPresent($this->password_selector, 30);
    if ($this->exts->querySelector($this->password_selector) != null) {

        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(4);

        $this->exts->log("Enter Password");
        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(3);

        if ($this->remember_me_selector != '')
            $this->exts->moveToElementAndClick($this->remember_me_selector);
        sleep(2);

        $this->exts->capture("2-login-page-filled");
        $this->exts->moveToElementAndClick($this->submit_login_selector);


        // if($this->exts->querySelector($this->password_selector) !== null && $this->exts->querySelector($this->password_alt_selector) !== null) {
        //     $this->exts->capture("2.1-login-page");

        //     $this->exts->log("Enter Username");
        //     $this->exts->moveToElementAndType($this->username_alt_selector, $this->username);
        //     sleep(1);

        //     $this->exts->log("Enter Password");
        //     $this->exts->moveToElementAndType($this->password_alt_selector, $this->password);
        //     sleep(1);

        //     if($this->remember_me_selector != '')
        //         $this->exts->moveToElementAndClick($this->remember_me_selector);
        //     sleep(2);

        //     $this->exts->capture("2-login-page-filled");
        //     $this->exts->moveToElementAndClick($this->submit_login_alt_selector);
        //     sleep(10);
        // }
    } else {
        $this->exts->log(__FUNCTION__ . '::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}
private function disable_uBlock_extensions()
{
    $this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm'); // disable Block origin extension
    sleep(2);
    $this->exts->execute_javascript("
	if(document.querySelector('extensions-manager') != null) {
		if(document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view')  != null){
			var disable_button = document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]');
			if(disable_button != null){
				disable_button.click();
			}
		}
	}
");
    sleep(1);
}

private function getInnerTextByJS($element)
{
    return $this->exts->evaluate("return arguments[0].innerText", [$element]);
}
private function processInvoices($paging_count = 1)
{
    $this->exts->waitTillPresent('div.kto-order-container');
    $this->exts->capture("4-invoices-page");
    $this->exts->log('processing page - ' . $paging_count);
    $invoices = [];
    if ($this->exts->exists('div.kto-order-container')) {
        $rows = $this->exts->querySelectorAll('div.kto-order-container');
        foreach ($rows as $row) {
            $doc_id_index = 0;
            $date_index = 0;
            $tags = $this->exts->querySelectorAll('.kto-order-label', $row);
            $tags_c = $this->exts->querySelectorAll('.kto-order-item', $row);
            if (count($tags) >= 5 && $this->exts->querySelector('a[href*="konto_tracking_detail"]', $row) != null) {
                $isOrderCompleted = false;
                foreach ($tags_c as $textRow) {
                    $temptext = trim($textRow->getText());
                    if (stripos($temptext, 'abgeschlossen') !== false && stripos($temptext, 'versendet') !== false) {
                        $isOrderCompleted = true;
                        $this->exts->log($temptext);
                        break;
                    }
                }

                if ($isOrderCompleted) {
                    foreach ($tags as $index => $tag) {
                        if (stripos(strtolower($this->getInnerTextByJS($tag)), 'auftrags-nr') !== false) {
                            $doc_id_index = $index;
                            $this->exts->log('found doc id index ' . $index);
                        }

                        if (stripos(strtolower($this->getInnerTextByJS($tag)), 'eingangsdatum') !== false || stripos(strtolower($this->getInnerTextByJS($tag)), 'entry date') !== false) {
                            $date_index = $index;
                            $this->exts->log('found date index ' . $index);
                        }
                    }
                    $invoiceUrl = $this->exts->querySelector('a[href*="konto_tracking_detail"]', $row)->getAttribute("href");
                    if (stripos($invoiceUrl, 'https://www.wir-machen-druck.de') === false) {
                        $invoiceUrl = 'https://www.wir-machen-druck.de' . $invoiceUrl;
                    }
                    $invoiceName = trim($this->getInnerTextByJS($tags_c[$doc_id_index]));
                    $invoiceDate = trim($this->getInnerTextByJS($tags_c[$date_index]));
                    $invoiceAmount = '';

                    $invoices[] = array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    );
                    $this->isNoInvoice = false;
                }
            }
        }
    } else {
        $rows = $this->exts->querySelectorAll('div.tracking-order-body');
        foreach ($rows as $row) {
            $doc_id_index = 0;
            $date_index = 0;
            $tags = $this->exts->querySelectorAll('.order-label', $row);
            $tags_c = $this->exts->querySelectorAll('.order-value', $row);
            if (count($tags) >= 5 && $this->exts->querySelector('a[href*="konto_tracking_detail"]', $row) != null) {
                $isOrderCompleted = false;
                foreach ($tags_c as $textRow) {
                    $temptext = trim($textRow->getText());
                    if (stripos($temptext, 'abgeschlossen') !== false && stripos($temptext, 'versendet') !== false) {
                        $isOrderCompleted = true;
                        $this->exts->log($temptext);
                        break;
                    }
                }

                if ($isOrderCompleted) {
                    foreach ($tags as $index => $tag) {
                        if (stripos(strtolower($this->getInnerTextByJS($tag)), 'auftrags-nr') !== false) {
                            $doc_id_index = $index;
                            $this->exts->log('found doc id index ' . $index);
                        }

                        if (stripos(strtolower($this->getInnerTextByJS($tag)), 'eingangsdatum') !== false || stripos(strtolower($this->getInnerTextByJS($tag)), 'entry date') !== false) {
                            $date_index = $index;
                            $this->exts->log('found date index ' . $index);
                        }
                    }
                    $invoiceUrl = $this->exts->querySelector('a[href*="konto_tracking_detail"]', $row)->getAttribute("href");
                    if (stripos($invoiceUrl, 'https://www.wir-machen-druck.de') === false) {
                        $invoiceUrl = 'https://www.wir-machen-druck.de' . $invoiceUrl;
                    }
                    $invoiceName = trim($this->getInnerTextByJS($tags_c[$doc_id_index]));
                    $invoiceDate = trim($this->getInnerTextByJS($tags_c[$date_index]));
                    $invoiceAmount = '';

                    $invoices[] = array(
                        'invoiceName' => $invoiceName,
                        'invoiceDate' => $invoiceDate,
                        'invoiceAmount' => $invoiceAmount,
                        'invoiceUrl' => $invoiceUrl
                    );
                    $this->isNoInvoice = false;
                }
            }
        }
    }

    $this->exts->openNewTab();
    sleep(2);
    $this->exts->switchToNewestActiveTab();
    // Download all invoices
    $this->exts->log('Invoices found: ' . count($invoices));
    sleep(2);
    foreach ($invoices as $invoice) {
        $this->exts->log('--------------------------');
        $this->exts->log('invoiceName: ' . $invoice['invoiceName']);
        $this->exts->log('invoiceDate: ' . $invoice['invoiceDate']);
        $this->exts->log('invoiceAmount: ' . $invoice['invoiceAmount']);
        $this->exts->log('invoiceUrl: ' . $invoice['invoiceUrl']);

        if ($this->exts->invoice_exists($invoice['invoiceName'])) {
            $this->exts->log('Invoice already exists - ' . $invoice['invoiceName']);
        } else {
            $invoiceFileName = $invoice['invoiceName'] . '.pdf';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y', 'Y-m-d');
            $this->exts->log('Date parsed: ' . $invoice['invoiceDate']);
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(8);

            if ($this->exts->exists('input[onclick*="showRechnung"], button[onclick*="showRechnung"]')) {
                $download_button = $this->exts->querySelector('input[onclick*="showRechnung"], button[onclick*="showRechnung"]');
                try {
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch (\Exception $exception) {
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }

                $downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
                if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
                    $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                    sleep(1);
                } else {
                    $this->exts->log(__FUNCTION__ . '::No download ' . $invoiceFileName);
                }
            }
        }
    }
    $this->exts->switchToInitTab();
    sleep(5);
    $this->exts->closeAllTabsButThis();
    sleep(2);

    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $link_cur_page = $this->exts->querySelector('a.order-page-active');
    $link_next_page = $this->exts->querySelector('.order-page-active + .order-page');
    if ($link_cur_page != null && $link_next_page != null) {
        $currentPageLink = trim($link_cur_page->getAttribute("href"));
        $this->exts->log($currentPageLink);
        $tempArr = explode('page=', $currentPageLink);
        $page_cur_num = end($tempArr);

        $nextPageLink = trim($link_next_page->getAttribute("href"));
        $this->exts->log($nextPageLink);
        $tempArr = explode('page=', $nextPageLink);
        $page_next_num = end($tempArr);
        $this->exts->log('Current Page - ' . $page_cur_num . ' Next Page - ' . $page_next_num);
        if ($restrictPages == 0 && $paging_count < 100 && $page_cur_num < $page_next_num) {
            $paging_count++;
            try {
                $this->exts->log('Click next button');
                $link_next_page->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click next button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$link_next_page]);
            }
            sleep(5);
            $this->exts->update_process_lock();
            $this->processInvoices($paging_count);
        } else if ($paging_count < 10 && $page_cur_num < $page_next_num) {
            $paging_count++;
            try {
                $this->exts->log('Click next button');
                $link_next_page->click();
            } catch (\Exception $exception) {
                $this->exts->log('Click next button by javascript');
                $this->exts->executeSafeScript("arguments[0].click()", [$link_next_page]);
            }
            sleep(5);
            $this->exts->update_process_lock();
            $this->processInvoices($paging_count);
        }
    }
}