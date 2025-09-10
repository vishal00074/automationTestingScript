<?php // migrated udpate login and download code // updated loginfailure code
// Server-Portal-ID: 9076 - Last modified: 16.07.2024 10:01:52 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://kundenportal.deutsche-glasfaser.de/kundenportal';
public $loginUrl = 'https://id.deutsche-glasfaser.de/accounts/login/';
public $invoicePageUrl = 'https://kundenportal.deutsche-glasfaser.de/kundenportal/#/home/rechnung/rechnungen';

public $username_selector = 'input[type="email"], input[name="username"]';
public $password_selector = 'input[name="password"]';
public $remember_me_selector = '#id_remember';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'form[action="/login/"] div[role="alertdialog"]';
public $check_login_success_selector = 'a[href="/authentication/logout"], .logout-wrapper, a[href="/logout/"], div.sc-contract-selection-item-text';

public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	$this->exts->openUrl($this->baseUrl);
	sleep(1);
	
	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);

	$this->close_cookie_alert();

	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();
		$this->exts->openUrl($this->loginUrl);
		sleep(15);

		$this->close_cookie_alert();
		$this->checkFillLogin();
        sleep(15);

		
        if($this->exts->getElement($this->check_login_success_selector) == null) {
			if($this->exts->exists('dg-button[data-sentry-element="DgButton"]')){
				$this->exts->log('Click on Login Button');
				$this->exts->moveToElementAndClick('dg-button[data-sentry-element="DgButton"]');
			}
			sleep(10);
        }

		$this->close_cookie_alert();
	}
	
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		if($this->exts->exists('app-notification-dialog button[name="next"]')) {
			$this->exts->moveToElementAndClick('app-notification-dialog button[name="next"]');
			sleep(1);
		}

		$this->close_cookie_alert();

		//If user was logged with cookie, it will skip account selection page. So we need to navigate to account selection page first.
		if ($this->exts->exists('[routerlink="/auth/login/vertrage"]')) {
			$this->exts->moveToElementAndClick('[routerlink="/auth/login/vertrage"]');
			sleep(15);
		}
		
		// Open invoices url and download invoice
		if ($this->exts->getElement('.contracts .contract-wrapper[id]') != null) {
			$contracts_array = [];
			
			$contracts = $this->exts->getElements('.contracts .contract-wrapper[id]');
			
			foreach ($contracts as $contract) {
				$contract_id = $contract->getAttribute('id');
				$this->exts->log("found account number: ".$contract_id);
				array_push($contracts_array, $contract_id);
			}
			foreach ($contracts_array as $key => $contract_id) {
				$this->exts->moveToElementAndClick('.contracts .contract-wrapper[id="'.$contract_id.'"]');
				sleep(10);
				$button_cookie = $this->exts->executeSafeScript('return document.querySelector("#usercentrics-root").shadowRoot.querySelector(\'button[data-testid="uc-accept-all-button"]\')');
				if($button_cookie != null){
					$this->exts->executeSafeScript("arguments[0].click()", [$button_cookie]);
					sleep(5);
				}
				if ($this->exts->getElement('a[href="#/home/rechnung"]') != null) {
					$this->exts->moveToElementAndClick('a[href="#/home/rechnung"]');
					sleep(10);
					if ($this->exts->getElement('.icon-billing-invoices') != null) {
						$this->exts->moveToElementAndClick('.icon-billing-invoices');
					} else {
						$this->exts->moveToElementAndClick('a[href="#/home/rechnung/rechnungen"]');
					}
					$this->processInvoices();
					sleep(2);
					// back to Dashboard
					$back_buttons = $this->exts->getElements('.logout-wrapper button');
					$this->exts->log('Finding Completted trips button...');
					foreach ($back_buttons as $key => $back_button) {
						$tab_name = strtolower($back_button->getAttribute('innerText'));
						if(stripos($tab_name, 'dashboard') !== false){
							$this->exts->log('Completted trips button found');
							try{
								$this->exts->log('Click button');
								$back_button->click();
							} catch(\Exception $exception){
								$this->exts->log('Click button by javascript');
								$this->exts->executeSafeScript("arguments[0].click()", [$back_button]);
							}
							sleep(10);
							break;
						}
					}
					if(!$this->exts->exists('.contracts .contract-wrapper[id]')){
						$this->exts->openUrl('https://kundenportal.deutsche-glasfaser.de/dashboard/');
						sleep(15);
					}
				}
			}
			
		} else if ($this->exts->exists('table > tbody > tr')) {
			$this->processMultiAccounts();
		} else if ($this->exts->getElement('a[href="#/home/rechnung"]') != null) {
			$this->exts->moveToElementAndClick('a[href="#/home/rechnung"]');
			sleep(10);
			if ($this->exts->getElement('.icon-billing-invoices') != null) {
				$this->exts->moveToElementAndClick('.icon-billing-invoices');
			} else {
				$this->exts->moveToElementAndClick('a[href="#/home/rechnung/rechnungen"]');
			}
			$this->processInvoices();
		}
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('div#id_username', null, 'innerText')), 'dieses feld wird ben') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('div#id_username', null, 'innerText')), 'gib eine') !== false && strpos(strtolower($this->exts->extract('div#id_username', null, 'innerText')), 'mail adresse an') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('[id*=mat-error]', null, 'innerText')), 'die angegebenen zugangsdaten stimmen nicht') !== false || strpos(strtolower($this->exts->extract('[id*=mat-error]', null, 'innerText')), 'access data do not match') !== false) {
			$this->exts->loginFailure(1);
		} else if(strpos(strtolower($this->exts->extract('[id*=mat-mdc-error]', null, 'innerText')), 'geben sie eine gültige') !== false) {
			$this->exts->loginFailure(1);
		} else if (strpos($this->exts->extract('div[id*="toast-danger"] div.font-normal font ', null, 'innerText'), 'Die E-Mail-Adresse und/oder das Passwort ist falsch.') !== false) {
			$this->exts->loginFailure(1);
		} else if (strpos($this->exts->extract('div[id*="toast-danger"] div.font-normal', null, 'innerText'), 'Die E-Mail-Adresse und/oder das Passwort ist falsch.') !== false) {
			$this->exts->loginFailure(1);
		} else if (strpos(strtolower($this->exts->extract('div[id*="toast-danger"] div.font-normal', null, 'innerText')), 'The email address and/or password is incorrect.') !== false) {
			$this->exts->loginFailure(1);
		}
		 elseif ($this->exts->exists('div.no-contracts')) {
			$this->exts->account_not_ready();
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function clearChrome()
{
    $this->exts->log("Clearing browser history, cookie, cache");
    $this->exts->openUrl('chrome://settings/clearBrowserData');
    sleep(10);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 2; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    $this->exts->type_key_by_xdotool('Return');
        sleep(1);
    $this->exts->type_key_by_xdotool('a');
    sleep(1);
    $this->exts->type_key_by_xdotool('Return');
    sleep(3);
    $this->exts->capture("clear-page");
    for ($i = 0; $i < 5; $i++) {
        $this->exts->type_key_by_xdotool('Tab');
        sleep(1);
    }
    $this->exts->type_key_by_xdotool('Return');
    sleep(15);
    $this->exts->capture("after-clear");
}

private function checkFillLogin() {
	if(!filter_var($this->username, FILTER_VALIDATE_EMAIL)){
		$this->exts->log('Username is not a valid email address.');
		$this->exts->loginFailure(1);
	}
	if($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");
		
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(2);
		
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(2);
		
		if($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);
		
		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function processMultiAccounts() {
	
	$account_len = count($this->exts->getElements('table > tbody > tr'));
	$this->exts->log('No of account: '. $account_len);
	for ($i = 0; $i < $account_len; $i++) {

		$getCurrentUrl = $this->exts->getUrl();

		$account_el = 'table > tbody > tr > td:nth-child(4) dg-button[data-testid="contract-table-row-'.$i.'-actions-select"]';
		
		try{
			$this->exts->log('Click account_el button');
			
			$this->exts->click_by_xdotool($account_el);
		} catch(\Exception $exception){
			$this->exts->log('Click account_el button by javascript');
			$this->exts->executeSafeScript("arguments[0].click()", [$account_el]);
		}
		sleep(15);

		$this->close_cookie_alert();

		$this->exts->moveToElementAndClick('button[name="remind-me-later"]');
		sleep(5);
		$this->exts->moveToElementAndClick('a[href*="/home/rechnung"]');
		sleep(15);

		$this->close_cookie_alert();

		if ($this->exts->getElement('.icon-billing-invoices') != null) {
			$this->exts->moveToElementAndClick('.icon-billing-invoices');
		} else {
			$this->exts->moveToElementAndClick('a[href="#/home/rechnung/rechnungen"]');
		}
		sleep(15);

		$this->close_cookie_alert();

		$this->processInvoices();

		$this->exts->moveToElementAndClick('[routerlink="/auth/login/vertrage"]');
		sleep(15);
		$this->close_cookie_alert();

		$this->exts->openUrl($getCurrentUrl);
		$this->exts->waitTillPresent($this->check_login_success_selector, 25);
		

	}
}

private function processInvoices($pageCount=1) {
    sleep(20);

    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = count($this->exts->getElements('sc-list-item-invoice mat-list-item'));
    for ($i=0; $i < $rows; $i++) {
        $row = $this->exts->getElements('sc-list-item-invoice mat-list-item')[$i];
        $tags = $this->exts->getElements('span.mdc-list-item__primary-text p.sc-list-item-line', $row);
        $this->exts->log('invoiceName: '.count($tags));
        
        if(count($tags) >=2 && $this->exts->getElement('.sc-actions button', $row) != null) {
            $this->isNoInvoice = false;
            $download_button = $this->exts->getElement('.sc-actions button', $row);
            $invoiceName = substr(trim(preg_replace('/[^\d\.\,]/', '', $tags[0]->getAttribute('innerText'))), 1);
            $invoiceFileName = $invoiceName.'.pdf';

            $invoiceDate = $this->exts->getElement('span.mdc-list-item__primary-text a.sc-list-item-title', $row)->getAttribute('innerText');
            $invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[1]->getAttribute('innerText'))) . ' EUR';
            $this->exts->log('--------------------------');
            $this->exts->log('invoiceName: '.$invoiceName);
            $this->exts->log('invoiceDate: '.$invoiceDate);
            $this->exts->log('invoiceAmount: '.$invoiceAmount);
            $parsed_date = $this->exts->parse_date($invoiceDate, 'd\. F Y','Y-m-d');
            $this->exts->log('Date parsed: '.$parsed_date);

            // Download invoice if it not existed
            if($this->exts->invoice_exists($invoiceName)){
                $this->exts->log('Invoice existed '.$invoiceFileName);
            } else {
                try{
                    $this->exts->log('Click download button');
                    $download_button->click();
                } catch(\Exception $exception){
                    $this->exts->log('Click download button by javascript');
                    $this->exts->executeSafeScript("arguments[0].click()", [$download_button]);
                }
                sleep(5);
                $this->exts->moveToElementAndClick('button[type="download-invoice"]');
                sleep(5);
                $this->exts->wait_and_check_download('pdf');
                $downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

                if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                    $this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $downloaded_file);
                } else {
                    $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                }
                $this->exts->moveToElementAndClick('button.sc-back-button');
                sleep(5);
                for ($i=1; $i < $pageCount; $i++) {
                    $this->exts->moveToElementAndClick('div.sc-pagination button:nth-child(2):not(:disabled)');
                    sleep(5);
                }
            }
        }
    }
    // next page
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	$this->exts->log('restrictPages' . $restrictPages);
    if( $pageCount < $restrictPages && $this->exts->getElement('div.sc-pagination button:nth-child(2):not(:disabled)') != null){
        $pageCount++;
        $this->exts->moveToElementAndClick('div.sc-pagination button:nth-child(2):not(:disabled)');
        sleep(1);
        $this->processInvoices($pageCount);
    }
}

function close_cookie_alert() {
	$str = "var div = document.querySelector('div#usercentrics-root'); if (div != null) {  div.style.display = \"none\"; }";
	$this->exts->execute_javascript($str);
	sleep(2);
}