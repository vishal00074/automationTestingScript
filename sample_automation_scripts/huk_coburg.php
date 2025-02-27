<?php
// Server-Portal-ID: 18492 - Last modified: 27.11.2024 13:13:45 UTC - User: 1

/*Define constants used in script*/
public $baseUrl = 'https://www.huk.de/login.do';
public $loginUrl = 'https://www.huk.de/login.do';
public $invoicePageUrl = '';

public $username_selector = 's-text-field input[type="email"],form#formZentralDataLogin input[name="TXT_B_KENNUNG"], input[name*="username-input"],input[name*="username"]';
public $password_selector = 'form#formZentralDataLogin input[name="TXT_PIN"], input[name*="password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'form#formZentralDataLogin a.genButton[name="weiter"],  form button[title*="Weiter"],  form button[title*="button__button"]';

public $check_login_failed_selector = '.message--error div.message__message';
public $check_login_success_selector = 'div.s-menu__menu [name="logout"]';

public $restrictPages = 3;
public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);
	
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;

	// $this->disable_unexpected_extensions();

	// Load cookies
	$this->exts->loadCookiesFromFile();
	sleep(1);
	$this->exts->openUrl($this->baseUrl);
	sleep(30);
	if($this->exts->getElementByCssSelector('button.cookie-consent__button--primary') != null) {
		$this->exts->moveToElementAndClick('button.cookie-consent__button--primary');
		sleep(5);
	}
	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElementByCssSelector($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->exts->openUrl($this->loginUrl);
		sleep(15);
		if($this->exts->exists('div[class*="login"] a[href*="start_kundenbereich"]')){
			$this->exts->log("Choose login button");
			$this->exts->moveToElementAndClick('div[class*="login"] a[href*="start_kundenbereich"]');
			sleep(25);
		}

		if($this->exts->exists('.s-huk-brand-header__right ul[slot="metaNavigationDesktop"] li a[href*="anmelden"]')){

			$this->exts->log("Choose login button new");
			$this->exts->moveToElementAndClick('.s-huk-brand-header__right ul[slot="metaNavigationDesktop"] li a[href*="anmelden"]');
			sleep(25);
		}

		$this->checkFillLogin();
		sleep(20);
		if ($this->exts->exists('huk-button[modifier="inverted"]')) {
			$this->exts->moveToElementAndClick('huk-button[modifier="inverted"]');
			sleep(20);
			$this->checkFillLogin();
			sleep(20);
		}
		$this->checkFillTwoFactor();
	}
	if($this->exts->urlContains('begruessung/uwg')){
		$button = $this->getElementByText('s-button button', 'Ablehnen', null, false);
		if($button != null) {
			try {
				$button->click();
			} catch (Exception $e) {
				$this->exts->executeSafeScript('arguments[0].click()', [$button]);
			}
			sleep(5);
			$this->checkFillLogin();
			sleep(20);
			$this->checkFillTwoFactor();
		}
		// $this->exts->moveToElementAndClick('button[title=Ablehnen]');
	}
	sleep(10);
	if($this->exts->getElementByCssSelector($this->check_login_success_selector) == null) {
		$this->exts->refresh();
		sleep(20);
	}
	if($this->exts->getElementByCssSelector($this->check_login_success_selector) != null) {
		sleep(3);
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		$this->exts->moveToElementAndClick('div#vertrag a[href="/start_kunde_uebersicht.do"]');
		sleep(15);
		$this->doAfterLogin();
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		if(strpos(strtolower($this->getInnerTextByJS($this->check_login_failed_selector)), 'passwor') !== false) {
			$this->exts->loginFailure(1);
		} else {
			$this->exts->loginFailure();
		}
	}
}

private function checkFillLogin() {
	if($this->exts->getElementByCssSelector($this->username_selector) != null) {
		sleep(3);
		$this->exts->capture("2-login-page");
		
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);
		for ($i=0; $i < 10 && $this->exts->exists('.frc-captcha .frc-progress'); $i++) { 
			sleep(8);
		}
		$this->exts->moveToElementAndClick('form s-button[variant="filled"] button');
		sleep(10);
		$this->exts->moveToElementAndClick($this->password_selector);
		sleep(5);
		for ($i=0; $i < 10 && $this->exts->exists('.frc-captcha .frc-progress'); $i++) { 
			sleep(8);
		}
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);
		for ($i=0; $i < 10 && $this->exts->exists('.frc-captcha .frc-progress'); $i++) { 
			sleep(15);
		}
		if($this->remember_me_selector != '')
			$this->exts->moveToElementAndClick($this->remember_me_selector);
		sleep(2);
		
		$this->exts->capture("2-login-page-filled");
		$this->exts->moveToElementAndClick($this->submit_login_selector);
		sleep(3);
		if(strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'passwor') !== false || strpos(strtolower($this->exts->extract($this->check_login_failed_selector, null, 'innerText')), 'e-mail-adresse') !== false) {
			$this->exts->loginFailure(1);
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function checkFillTwoFactor() {
	$two_factor_selector = '[name="pin"] input#pin-input';
	$two_factor_message_selector = 'form > div > p.text--default';
	$two_factor_submit_selector = 'form [variant="cta"] button.button__button';

	if($this->exts->getElementByCssSelector($two_factor_selector) != null && $this->exts->two_factor_attempts < 3){
		$this->exts->log("Two factor page found.");
		$this->exts->capture("2.1-two-factor");

		if($this->exts->getElementByCssSelector($two_factor_message_selector) != null){
			$this->exts->two_factor_notif_msg_en = "";
			for ($i=0; $i < count($this->exts->getElementsByCssSelector($two_factor_message_selector)); $i++) { 
				$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en.$this->exts->getElementsByCssSelector($two_factor_message_selector)[$i]->getText()."\n";
			}
			$this->exts->two_factor_notif_msg_en = trim($this->exts->two_factor_notif_msg_en);
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_en;
			$this->exts->log("Message:\n".$this->exts->two_factor_notif_msg_en);
		}
		if($this->exts->two_factor_attempts == 2) {
			$this->exts->two_factor_notif_msg_en = $this->exts->two_factor_notif_msg_en .' '. $this->exts->two_factor_notif_msg_retry_en;
			$this->exts->two_factor_notif_msg_de = $this->exts->two_factor_notif_msg_de .' '. $this->exts->two_factor_notif_msg_retry_de;
		}

		$two_factor_code = trim($this->exts->fetchTwoFactorCode());
		if(!empty($two_factor_code) && trim($two_factor_code) != '') {
			$this->exts->log("checkFillTwoFactor: Entering two_factor_code.".$two_factor_code);
			$this->exts->getElementByCssSelector($two_factor_selector)->sendKeys($two_factor_code);
			
			$this->exts->log("checkFillTwoFactor: Clicking submit button.");
			sleep(3);
			$this->exts->capture("2.2-two-factor-filled-".$this->exts->two_factor_attempts);

			$this->exts->moveToElementAndClick($two_factor_submit_selector);
			sleep(15);

			if($this->exts->getElementByCssSelector($two_factor_selector) == null){
				$this->exts->log("Two factor solved");
			} else if ($this->exts->two_factor_attempts < 3) {
				$this->exts->notification_uid = "";
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

private function disable_unexpected_extensions(){
	$this->exts->openUrl('chrome://extensions/?id=cjpalhdlnbpafiamejdnhcphjbkeiagm');// disable Block origin extension
	sleep(2);
	$this->exts->executeSafeScript("
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
	$this->exts->openUrl('chrome://extensions/?id=ifibfemgeogfhoebkmokieepdoobkbpo');
	sleep(1);
		$this->exts->executeSafeScript("if (document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]') != null) {
			document.querySelector('extensions-manager').shadowRoot.querySelector('extensions-detail-view').shadowRoot.querySelector('#enableToggle[checked]').click();
		}");
	sleep(2);
}

function getInnerTextByJS($selector_or_object, $parent = null){
	if($selector_or_object == null){
		$this->exts->log(__FUNCTION__.' Can not get innerText of null');
		return;
	}
	$element = $selector_or_object;
	if(is_string($selector_or_object)){
		$element = $this->exts->getElement($selector_or_object, $parent);
		if($element == null){
			$element = $this->exts->getElement($selector_or_object, $parent, 'xpath');
		}
		if($element == null){
			$this->exts->log(__FUNCTION__.':: Can not found element with selector/xpath: '. $selector_or_object);
		}
	}
	if ($element != null) {
		return $this->exts->executeSafeScript("return arguments[0].innerText", [$element]);
	}
}

private function getElementByText($selector, $multi_language_texts, $parent_element=null, $is_absolutely_matched=true){
	$this->exts->log(__FUNCTION__);
	if(is_array($multi_language_texts)){
		$multi_language_texts = join('|', $multi_language_texts);
	}
	// Seaching matched element
	$object_elements = $this->exts->getElements($selector, $parent_element);
	foreach ($object_elements as $object_element) {
		$element_text = trim($object_element->getAttribute('textContent'));
		// First, search via text
		// If is_absolutely_matched = true, seach element matched EXACTLY input text, else search element contain the text
		if($is_absolutely_matched){
			$multi_language_texts = explode('|', $multi_language_texts);
			foreach ($multi_language_texts as $searching_text) {
				if(strtoupper($element_text) == strtoupper($searching_text)){
					$this->exts->log('Matched element found');
					return $object_element;
				}
			}
			$multi_language_texts = join('|', $multi_language_texts);
		} else {
			if(preg_match('/'.$multi_language_texts.'/i', $element_text) === 1){
				$this->exts->log('Matched element found');
				return $object_element;
			}
		}

		// Second, is search by text not found element, support searching by regular expression
		if(@preg_match($multi_language_texts, '') !== FALSE){
			if(preg_match($multi_language_texts, $element_text) === 1){
				$this->exts->log('Matched element found');
				return $object_element;
			}
		}
	}
	return null;
}

private function doAfterLogin() {
	sleep(10);
	
	//Download document from mailbox;
	$this->exts->moveToElementAndClick('[class*="-meinehuk"], div.s-menu__activator a[href="/"]');
	sleep(1);
	
	$this->exts->moveToElementAndClick('#meinehuk a[href="/start_postfach.do"], #meinehuk a[href*="/postfach"], .s-menu__menu a[href*="/postfach"], [class*=cardlink][href*="meine-huk"]');
	sleep(15);
	
	$this->procecssMailBox();
	
	if($this->restrictPages == 0) {
		// Collect contracts and download document from archive
		$contracts = $this->exts->getElementsAttribute('div.vertraege > div.table_row a[href*="vertrag"]', 'href');
		$this->exts->log('Num Of Contracts found: '.count($contracts));
		foreach ($contracts as $key => $contract_url) {
			sleep(3);
			$contractName = array_pop(explode('&vertragid=', $contract_url));
			$this->exts->log('GO TO CONTRACTS: '.$contractName);
			$this->exts->openUrl($contract_url);
			sleep(15);
			// Open invoice tab
			$this->exts->moveToElementAndClick('a.tabname-Archiv');
			sleep(10);
			$this->processInvoices();
		}
	}
	
	// Final, check no invoice
	if($this->isNoInvoice){
		$this->exts->no_invoice();
	}
	$this->exts->success();
}

private function processInvoices() {
	sleep(25);
	$this->exts->capture("4-invoices-page");
	$invoices = [];
	
	$rows = $this->exts->getElementsByCssSelector('div.postfach > div.table_row');
	foreach ($rows as $row) {
		$tags = $this->exts->getElementsByCssSelector('div.col', $row);
		if(count($tags) >= 4 && $this->exts->getElementByCssSelector('a[href*="/Dokument"]', $tags[3]) != null) {
			$invoiceUrl = $this->exts->getElementByCssSelector('a[href*="/Dokument"]', $tags[3])->getAttribute("href");
			$invoiceName =array_pop(explode('&dokument=', $invoiceUrl));
			$invoiceDate = trim($this->getInnerTextByJS($tags[1]));
			$invoiceAmount = '';
			
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		if(!$this->exts->invoice_exists($invoice['invoiceName'])) {
			//We don't need to pass invoicefilename here because we want the same file name as website provide when get downloaded.
			$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', '');
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		} else {
			$this->exts->log(__FUNCTION__.'::Invoice already exists '.$invoiceFileName);
		}
	}
}

public function procecssMailBox() {
	$this->exts->capture("4-mailbox-page");
	$invoices = [];
	
	$rows = $this->exts->getElementsByCssSelector('a.row');
	foreach ($rows as $row) {
		$tags = $this->exts->getElementsByCssSelector('div.col', $row);
		if(count($tags) >= 3 && $this->exts->getElementByCssSelector('a[href*="/Dokument"]', $tags[2]) != null) {
			$invoiceUrl = $this->exts->getElementByCssSelector('a[href*="/Dokument"]', $tags[2])->getAttribute("href");
			$invoiceName =array_pop(explode('&dokument=', $invoiceUrl));
			$invoiceDate = trim($this->getInnerTextByJS($tags[1]));
			$invoiceAmount = '';
			
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
		$invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd.m.Y','Y-m-d');
		$this->exts->log('Date parsed: '.$invoice['invoiceDate']);
		
		if(!$this->exts->invoice_exists($invoice['invoiceName'])) {
			//We don't need to pass invoicefilename here because we want the same file name as website provide when get downloaded.
			$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', '');
			if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			}
		} else {
			$this->exts->log(__FUNCTION__.'::Invoice already exists '.$invoiceFileName);
		}
	}

	if ($this->isNoInvoice) {
		// With this new design of inbox page, we have two case:
		// 1. If document mail is unread, It display inline download button attached to mail, so We click it to download document
		// 2. If document mails have already read, We need to go in mail detail and they have "Download" button there for downloading the document.
		$count_rows = count($this->exts->getElements('div.content-block__body > a.row,div.content-block__body > div > a.row'));
		for ($i=0; $i < $count_rows; $i++) {
			$row = $this->exts->getElements('div.content-block__body > a.row,div.content-block__body > div > a.row')[$i];
			$inline_download_button = $this->exts->getElement('.icon--inline-download', $row);
			if($inline_download_button != null){
				$this->exts->log('Un-read-mail: ' . $row->getText());
				try{
					$this->exts->log('Click inline_download_button');
					$inline_download_button->click();
				} catch(\Exception $exception){
					$this->exts->log('Click inline_download_button by javascript');
					$this->exts->executeSafeScript("arguments[0].click()", [$inline_download_button]);
				}
				sleep(5);
				$this->exts->wait_and_check_download('pdf');
				$downloaded_file = $this->exts->find_saved_file('pdf', '');
				
				if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
					$invoiceName = basename($downloaded_file);
					$this->exts->new_invoice($invoiceName, '', '', $downloaded_file);
				} else {
					$this->exts->log(__FUNCTION__.'::Waiting Pdf timeout');
				}
				$this->isNoInvoice = false;
			} else {
				$invoiceUrl = $row->getAttribute("href");
				if (stripos($invoiceUrl, 'https://www.huk.de/meine-huk/postfach/') === false) {
					$invoiceUrl = 'https://www.huk.de/meine-huk/postfach/' . $invoiceUrl;
				}
				$temps = explode('/postfach/', $invoiceUrl);
				$invoiceName = end($temps);
				$invoiceDate = '';
				$invoiceAmount = '';
				
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
		$this->exts->log('Detail mails found: '.count($invoices));
		foreach ($invoices as $invoice) {
			$this->exts->log('--------------------------');
			$this->exts->log('invoiceName: '.$invoice['invoiceName']);
			$this->exts->log('invoiceDate: '.$invoice['invoiceDate']);
			$this->exts->log('invoiceAmount: '.$invoice['invoiceAmount']);
			$this->exts->log('invoiceUrl: '.$invoice['invoiceUrl']);
			
			$this->exts->openUrl($invoice['invoiceUrl']);
			sleep(10);
			$this->exts->moveToElementAndClick('[title="Herunterladen"], [title*="Download"], [href*=download]');
			sleep(5);
			$this->exts->wait_and_check_download('pdf');
			$downloaded_file = $this->exts->find_saved_file('pdf', '');
			if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
				$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
				sleep(1);
			} else {
				$this->exts->log(__FUNCTION__.'::No download '.$invoice['invoiceName']);
			}

			// if(!$this->exts->invoice_exists($invoice['invoiceName'])) {
			// 	//We don't need to pass invoicefilename here because we want the same file name as website provide when get downloaded.
			// 	$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', '');
			// 	if (trim($downloaded_file) != '' && file_exists($downloaded_file)) {
			// 		$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $downloaded_file);
			// 		sleep(1);
			// 	} else {
			// 		$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
			// 	}
			// } else {
			// 	$this->exts->log(__FUNCTION__.'::Invoice already exists '.$invoiceFileName);
			// }
		}
	}
}