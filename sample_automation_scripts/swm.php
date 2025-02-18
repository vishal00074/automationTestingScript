<?php // migrated
// Server-Portal-ID: 163752 - Last modified: 04.11.2024 13:11:10 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://meine.swm.de';
public $invoicePageUrl = 'https://meine.swm.de/iss/ui/#/acc/postfach';

public $username_selector = 'input#email';
public $password_selector = 'input#password';
public $remember_me_selector = '';
public $submit_login_selector = 'button[type="submit"]';

public $check_login_failed_selector = 'app-login-error-message p.message--error';
public $check_login_success_selector = 'a#logout-button, button#btnLogout, div.user-logout';

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
    sleep(1);	
	$this->exts->openUrl($this->baseUrl);
	sleep(5);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page');

	if($this->exts->exists('#cmpwelcomebtnyes a')){
		$this->exts->moveToElementAndClick('#cmpwelcomebtnyes a');
		sleep(2);
	}

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		// $this->exts->clearCookies();
		$this->exts->openUrl($this->baseUrl);
		sleep(5);
		$this->exts->moveToElementAndClick('img[alt="Login mit M-Login"]');
		sleep(10);

		//You are currently logged in as username
		if($this->exts->exists('div.form-register-holder button#submitContinue')){
			$this->exts->moveToElementAndClick('div.form-register-holder button#submitContinue');
			sleep(10);
		}
		$this->check_solve_blocked_page();
		$this->checkFillLogin();
		sleep(15);
		if($this->exts->exists('form.register-form button[type="submit"]')){
			$this->exts->moveToElementAndClick('form.register-form button[type="submit"]');
			sleep(10);
		}
		if ($this->exts->urlContains('/acc/vkhinzufuegen') && $this->exts->exists('input#vknummer')) {
			$this->exts->account_not_ready();
		}
		if ($this->exts->urlContains('/account/update') && $this->exts->exists('div.form-register-holder')) {
			$Notnow_button = $this->exts->getElement('//form[contains(@class, "register-form")]//a[contains(text(),"Nein, danke. Jetzt nicht")]', null, 'xpath');
			if($Notnow_button != null){
				try{
                $this->exts->log('Click Notnow button');
	                $Notnow_button->click();
	            } catch(\Exception $exception){
	                $this->exts->log('Click Notnow button by javascript');
	                $this->exts->execute_javascript("arguments[0].click()", [$Notnow_button]);
	            }
				sleep(10);
			}

		}


	}

	if ($this->exts->getElement('.form-register-holder .ng-star-inserted .switch__box') != null && $this->exts->getElement($this->check_login_failed_selector) == null) {
		$this->exts->moveToElementAndClick('.form-register-holder .ng-star-inserted .switch__box');
		sleep(5);
		$this->exts->moveToElementAndClick('.form-register-holder #approvalSubmit');
		sleep(20);
	}

	if($this->exts->getElement($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		if($this->exts->exists('a[href*="postfach"]')){
			$this->exts->moveToElementAndClick('a[href*="postfach"]');
		}else {
			// Open invoices url and download invoice
			$this->exts->execute_javascript('location.href = "https://meine.swm.de/iss/ui/#/acc/postfach"');
		}

		$this->processInvoices();
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
		
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->exts->extract('app-login-error-message .message--error')), 'e-mail-adresse oder passwort') !== false) {
			$this->exts->loginFailure(1);
		} else if($this->exts->urlContains('account/update/agb-and-approval')) {
			$this->exts->account_not_ready();
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin() {
	if($this->exts->getElement($this->password_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);

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

private function check_solve_blocked_page() {
    $this->exts->capture_by_chromedevtool("blocked-page-checking");
    if($this->exts->exists('ngx-turnstile.ng-untouched div')){
        $this->exts->capture_by_chromedevtool("blocked-by-cloudflare");
        $this->exts->click_by_xdotool('ngx-turnstile.ng-untouched div',60, 28, true);
        sleep(20);
        if($this->exts->exists('ngx-turnstile.ng-untouched div')){
            $this->exts->click_by_xdotool('ngx-turnstile.ng-untouched div', 60, 28, true);
            sleep(20);
        }
        if($this->exts->exists('ngx-turnstile.ng-untouched div')){
            $this->exts->click_by_xdotool('ngx-turnstile.ng-untouched div', 60, 28, true);
            sleep(20);
        }
    }
}

private function processInvoices() {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	$restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	if($restrictPages == 0 && $this->exts->exists('.radio .radio-item')){
		$paging_count = count($this->exts->getElements('.radio .radio-item'));
	} else {
		$paging_count = 1;
	}
	for ($j=0; $j < $paging_count; $j++) {
		$rows = count($this->exts->getElements('table > tbody > tr'));
		for ($i=0; $i < $rows; $i++) {
			$row = $this->exts->getElements('table > tbody > tr')[$i];
			$tags = $this->exts->getElements('td', $row);
			if($this->exts->getElement('a.link-download', $tags[1]) != null) {
				$this->isNoInvoice = false;
				$download_button = $this->exts->getElement('a', $tags[1]);
				$invoiceName = trim(preg_replace('/([0-9]{0,2}).([0-9]{2}).([0-9]{4})/', '$1$2$3', $tags[0]->getAttribute('innerText')));
				$invoiceFileName = $invoiceName.'.pdf';
				$invoiceDate = trim($tags[0]->getAttribute('innerText'));
				$invoiceAmount = '';

				$this->exts->log('--------------------------');
				$this->exts->log('invoiceName: '.$invoiceName);
				$this->exts->log('invoiceDate: '.$invoiceDate);
				$this->exts->log('invoiceAmount: '.$invoiceAmount);
				$parsed_date = $this->exts->parse_date($invoiceDate, 'd.m.Y','Y-m-d');
				$this->exts->log('Date parsed: '.$parsed_date);

				// Download invoice if it not exisited
				if($this->exts->invoice_exists($invoiceName)){
					$this->exts->log('Invoice existed '.$invoiceFileName);
				} else {
					try{
						$this->exts->log('Click download button');
						$download_button->click();
					} catch(\Exception $exception){
						$this->exts->log('Click download button by javascript');
						$this->exts->execute_javascript("arguments[0].click()", [$download_button]);
					}
					sleep(5);
					$this->exts->wait_and_check_download('pdf');
					$downloaded_file = $this->exts->find_saved_file('pdf', $invoiceFileName);

					if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
						$this->exts->new_invoice($invoiceName, $parsed_date, $invoiceAmount, $invoiceFileName);
					} else {
						$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
					}
				}
			}
		}
		if($restrictPages == 0 && $this->exts->exists('.radio .radio-item input[id="v-auswahl-radio-option'.($j+1).'"]')){
			$this->exts->getElements('.radio .radio-item')[$j+1]->click();
			sleep(5);
		} else {
			break;
		}
	}
}