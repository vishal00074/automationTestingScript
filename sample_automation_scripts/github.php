<?php // updated login code
// Server-Portal-ID: 419 - Last modified: 23.01.2025 14:54:11 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://github.com/account/billing/history';

public $username_selector = 'input[name="login"]';
public $password_selector = 'input[name="password"]';
public $submit_login_selector = 'input[name="commit"]';
public $check_login_success_selector = 'header notification-indicator';

public $isNoInvoice = true; 
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(10);
	$this->exts->capture('1-init-page');

	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->querySelector($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->clearCookies();// added this to clear cookie since this was converted from casperjs.
		$this->exts->openUrl($this->baseUrl);
		sleep(10);
		$this->checkFillLogin();
		sleep(7);
		
		$this->checkFillTwoFactor();
	}

	if ($this->exts->exists('form[action="/settings/two_factor_checkup/delay"] button')) {
		$this->exts->moveToElementAndClick('form[action="/settings/two_factor_checkup/delay"] button');
		sleep(10);
	}

	if ($this->exts->exists('button[value="postponed"]')) {
		$this->exts->moveToElementAndClick('button[value="postponed"]');
		sleep(10);
	}

	// then check user logged in or not
	if($this->exts->querySelector($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");

		// Open personal invoice page
		$this->exts->openUrl('https://github.com/account/billing/history');
		$this->processInvoices();

		// Fetch all multil organizations and download invoice for each org.
		$this->exts->openUrl('https://github.com/settings/organizations');
		sleep(5);
		$this->exts->capture("3-organizations-checking");
		$organizations = $this->exts->getElementsAttribute('.Layout-main a[href*="/organizations/"][href*="/settings"]', 'href');
		foreach ($organizations as $key => $organization_url) {
			$this->exts->openUrl($organization_url);
			sleep(5);
			$this->exts->moveToElementAndClick('.NavigationList a[href*="settings/billing"]');
			sleep(5);
			$this->exts->moveToElementAndClick('a[href*="/billing/history"]');
			$this->processInvoices();
		}

		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->openUrl('https://github.com/account/billing/history');
			$this->exts->no_invoice();
		}
		$this->exts->success();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		$this->exts->log(__FUNCTION__.'::Last URL: '. $this->exts->getUrl());
		if(stripos($this->exts->extract('#login .flash-error'), 'passwor') !== false) {
			$this->exts->loginFailure(1);
		} else if($this->exts->exists('/password_reset')){
			$this->exts->account_not_ready();
		} else {
			$this->exts->loginFailure();
		}
	}
}
private function checkFillLogin() {
	if($this->exts->querySelector($this->password_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");

		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);

		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);

		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
        sleep(10);

        if ($this->exts->exists('iframe[src*="octocaptcha"]')) {
            $this->switchToFrame('iframe[src*="octocaptcha"]');
            sleep(2);
        }
        if ($this->exts->exists('iframe[src*="arkoselabs"][title="Verification challenge"]')) {
            $this->switchToFrame('iframe[src*="arkoselabs"][title="Verification challenge"]');
            sleep(2);
        }


        $this->exts->waitTillPresent('iframe[id="game-core-frame"]');
        if ($this->exts->exists('iframe[id="game-core-frame"]')) {
            $this->switchToFrame('iframe[id="game-core-frame"]');
            $this->exts->moveToElementAndClick('button[data-theme="home.verifyButton"]');
            $this->exts->waitTillPresent('img[aria-labelledby="key-frame-text"]');
            if ($this->exts->exists('img[aria-labelledby="key-frame-text"]')) {
                $this->exts->processRotateCaptcha('div[class*="sc-99cwso-0 sc-1t9on73-0 gMEQEa cOLSza box challenge-container"]', 'a[aria-label="Navigate to next image"]', 'a[aria-label="Navigate to previous image"]', 'button.button');
            }
        }

    } else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
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
private function checkFillTwoFactor() {
	$this->exts->waitTillPresent('iframe[id="game-core-frame"]');
	if($this->exts->exists('iframe[id="game-core-frame"]')){
		$this->switchToFrame('iframe[id="game-core-frame"]');
		$this->exts->click_by_xdotool('button[data-theme="home.verifyButton"]');
		$this->exts->waitTillPresent('img[aria-labelledby="key-frame-text"]');
		if($this->exts->exists('img[aria-labelledby="key-frame-text"]')){
			$this->exts->processRotateCaptcha('div[class*="sc-99cwso-0 sc-1t9on73-0 gMEQEa cOLSza box challenge-container"]','a[aria-label="Navigate to next image"]', 'a[aria-label="Navigate to previous image"]', 'button.button');
		}
	}
	//data-theme="home.verifyButton"
	if($this->exts->exists('[data-target="webauthn-get.button"]')){
		$this->exts->moveToElementAndClick('a[href*="/two-factor/app"]');
		sleep(5);
	}
	if ($this->exts->exists('a[href="/sessions/two-factor/sms"]')) {
		$this->exts->moveToElementAndClick('a[href="/sessions/two-factor/sms"]');
		sleep(5);
	}
	
	if ($this->exts->exists('a[href*="two_factor_app_prompt"]')) {
		$this->exts->moveToElementAndClick('a[href*="two_factor_app_prompt"]');
		sleep(5);
	}
	if ($this->exts->exists('a[href*="two_factor_sms_confirm"]')) {
		$this->exts->moveToElementAndClick('a[href*="two_factor_sms_confirm"]');
		sleep(5);
		if($this->exts->urlContains('sms/confirm')){
			$this->exts->moveToElementAndClick('button[type=submit]');
		}
	}
	if ($this->exts->exists('form[action="/sessions/two-factor/sms/confirm"] button.js-octocaptcha-form-submit')) {
		$this->exts->moveToElementAndClick('form[action="/sessions/two-factor/sms/confirm"] button.js-octocaptcha-form-submit');
		sleep(5);
	}
	if($this->exts->allExists(['a[href="/sessions/two-factor/mobile_metrics?auto=true&reason=verified_devices_prompt"]', '#github-mobile-authenticate-prompt'])){
		$this->exts->capture("2.1-two-factor-prompt");
		$this->exts->moveToElementAndClick('a[href="/sessions/two-factor/mobile_metrics?auto=true&reason=verified_devices_prompt"]');
		sleep(10);
	} else if($this->exts->allExists(['a[href="/sessions/two-factor/mobile_metrics?auto=true&reason=two_factor_prompt"]', '#github-mobile-authenticate-prompt'])){
		$this->exts->capture("2.1-two-factor-prompt");
		$this->exts->moveToElementAndClick('a[href="/sessions/two-factor/mobile_metrics?auto=true&reason=two_factor_prompt"]');
		sleep(10);
	} else if($this->exts->urlContains('/two-factor/webauthn')){
		$this->exts->moveToElementAndClick('a[href="/sessions/two-factor"]');// select other method
		sleep(5);
	}
	


	$two_factor_selector = 'input[name*="otp"]';
	$two_factor_message_selector = '.two-factor-help > p:first-child, form[action*="/two-factor"] .mt-3';
	$two_factor_submit_selector = 'form[action*="/two-factor"] button[type="submit"].btn-primary, form[action*="/verified-device"] button[type="submit"].btn-primary';
	if($this->exts->exists($two_factor_selector)){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");
		
		if($this->exts->exists('.two-factor-help > p:first-child')){
			$this->exts->two_factor_notif_msg_en = trim($this->exts->extract('.two-factor-help > p:first-child'));
		} else if($this->exts->exists('form[action*="/two-factor"] > div.mt-3')){
			$this->exts->two_factor_notif_msg_en = trim($this->exts->extract('form[action*="/two-factor"] > div.mt-3'));
			$this->exts->two_factor_notif_msg_en = explode("\n", $this->exts->two_factor_notif_msg_en)[0];
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
		}
		$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}

		$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		$this->exts->notification_uid = "";
		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->moveToElementAndType($two_factor_selector, $two_factor_code);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);
			sleep(1);
			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(10);

			if($this->exts->querySelector($two_factor_selector) == null){
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

private function processInvoices() {
	sleep(15);
	$this->exts->capture("4-invoices-page");
	$invoices = [];

	$rows = $this->exts->getElements('.payment-history li');
	foreach ($rows as $row) {
		$download_link = $this->exts->getElement('.receipt a', $row);
		if($download_link != null) {
			$invoiceUrl = $download_link->getAttribute("href");
			$invoiceName = trim($this->exts->extract('.id', $row));
			$invoiceDate = trim($this->exts->extract('.date', $row));
			$invoiceAmount = preg_replace('/[^\d\.\,]/', '', $this->exts->extract('.amount', $row)) . ' USD';

			array_push($invoices, array(
				'invoiceName'=>$invoiceName,
				'invoiceDate'=>$invoiceDate,
				'invoiceAmount'=>$invoiceAmount,
				'invoiceUrl'=>$invoiceUrl
			));
			$this->isNoInvoice = false;
		}
	}

	// Download all invoices
	$this->exts->log('Invoices found: '.count($invoices));
	foreach ($invoices as $invoice) {
		$this->exts->log('--------------------------');
		$this->exts->log('invoiceName: '.$invoice['invoiceName']);
		$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
		$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
		$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);

		$invoiceFileName = $invoice['invoiceName'].'.pdf';
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
			sleep(1);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}