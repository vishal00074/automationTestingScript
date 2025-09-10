<?php
// Server-Portal-ID: 335 - Last modified: 10.12.2024 14:54:24 UTC - User: 1

// Script here
public $baseUrl = 'https://admin.df.eu/kunde/index.php5';
public $username_selector = 'form input[name="identifier"]';
public $password_selector = 'form input[name="password"]';
public $submit_login_selector = 'form button[type="submit"]';
public $check_login_failed_selector = '.messages .mark_error';
public $check_login_success_selector = 'a[href*="module=logout"]';

public $restrictPages = 3;
public $isNoInvoice = true;
/**
 * Entry Method thats called for a portal
 * @param Integer $count Number of times portal is retried.
 */
private function initPortal($count) {
	$this->exts->log('Begin initPortal '.$count);

	$this->exts->loadCookiesFromFile();
	
	$this->restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
	
	$this->exts->openUrl($this->baseUrl);
	$this->exts->waitTillAnyPresent([$this->check_login_success_selector, 'a[href*="sso.df.eu/"][href*="kunde"]']);
	$this->exts->capture('1-init-page');
	
	// If user hase not logged in from cookie, clear cookie, open the login url and do login
	if($this->exts->getElement($this->check_login_success_selector) == null) {
		$this->exts->log('NOT logged via cookie');
		$this->checkFillLogin();
		$this->exts->waitTillPresent($this->check_login_success_selector);
		if (stripos($this->exts->extract('main span > span'), 'Es ist ein reCAPTCHA-Fehler aufgetreten') !== false) {
			$this->exts->refresh();
			$this->checkFillLogin();
			$this->exts->waitTillPresent($this->check_login_success_selector);
		}
	}
	
	// then check user logged in or not
	if($this->exts->getElement($this->check_login_success_selector) != null) {
		$this->exts->log(__FUNCTION__.'::User logged in');
		$this->exts->capture("3-login-success");
		
		// Open invoices url and download invoice
		$this->exts->openUrl(explode('?', $this->exts->getUrl())[0].'?module=rechnungen');
		$this->processInvoices();
		
		// Final, check no invoice
		if($this->isNoInvoice){
			$this->exts->no_invoice();
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Use login failed');
		$errorMessage = $this->exts->extract('main');
		$this->exts->log('Error message - '.$errorMessage);
		if(stripos($errorMessage, 'Benutzername oder Passwort sind nicht korrekt') !== false) {
			$this->exts->loginFailure(1);
		} else {
			$errorMessage = $this->exts->extract('main span > span');
			$this->exts->log('Error message - '.$errorMessage);
			if(stripos($errorMessage, 'Login nicht m') !== false || stripos(strtolower($errorMessage), 'login not possible') !== false) {
				$this->exts->loginFailure(1);
			} else {
				$this->exts->loginFailure();
			}
		}
	}
}

private function checkFillLogin() {
	if($this->exts->exists('a[href*="sso.df.eu/"][href*="kunde"]')) {
		$this->exts->moveToElementAndClick('a[href*="sso.df.eu/"][href*="kunde"]');
	}
	sleep(5);
	$this->exts->waitTillPresent($this->password_selector);
	if($this->exts->getElement($this->password_selector) != null) {
		$this->exts->capture("2-login-page");
		
		$this->exts->log("Enter Username");
		$this->exts->moveToElementAndType($this->username_selector, $this->username);
		sleep(1);
		
		$this->exts->log("Enter Password");
		$this->exts->moveToElementAndType($this->password_selector, $this->password);
		sleep(1);
		
		$this->exts->capture("2-login-page-filled");
		$this->checkFillRecaptcha();
		if($this->exts->exists($this->submit_login_selector)){
			$this->exts->moveToElementAndClick($this->submit_login_selector);
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Login page not found');
		$this->exts->capture("2-login-page-not-found");
	}
}

private function checkFillRecaptcha() {
	$this->exts->log(__FUNCTION__);
	$recaptcha_iframe_selector = 'form#sign-in-form #captcha:not(.is-hidden-js) iframe[src*="/recaptcha/api2/anchor?"], iframe[src*="/recaptcha/api2/anchor?"]';
	$recaptcha_textarea_selector = 'textarea[name="g-recaptcha-response"]';
	if($this->exts->exists($recaptcha_iframe_selector)) {
		$iframeUrl = $this->exts->extract($recaptcha_iframe_selector, null, 'src');
		$data_siteKey = explode('&', end(explode("&k=", $iframeUrl)))[0];
		$this->exts->log("iframe url  - " . $iframeUrl);
		$this->exts->log("SiteKey - " . $data_siteKey);
		
		$isCaptchaSolved = $this->processRecaptcha(trim($this->exts->getUrl()), $data_siteKey, false);
		$this->exts->log("isCaptchaSolved - " . $isCaptchaSolved);
		
		if($isCaptchaSolved) {
			// Step 1 fill answer to textarea
			$this->exts->log(__FUNCTION__."::filling reCaptcha response..");
			$recaptcha_textareas =  $this->exts->getElements($recaptcha_textarea_selector);
			for ($i=0; $i < count($recaptcha_textareas); $i++) {
				$this->exts->execute_javascript("arguments[0].innerHTML = '" .$this->exts->recaptcha_answer. "';", [$recaptcha_textareas[$i]]);
			}
			sleep(2);
			$this->exts->capture('recaptcha-filled');
			
			// Step 2, check if callback function need executed
			$gcallbackFunction = $this->exts->execute_javascript('
				if(document.querySelector("[data-callback]") != null){
					document.querySelector("[data-callback]").getAttribute("data-callback");
				}

				var result = ""; var found = false;
				function recurse (cur, prop, deep) {
					if(deep > 5 || found){ }console.log(prop);
					try {
						if(cur == undefined || cur == null || cur instanceof Element || Object(cur) !== cur || Array.isArray(cur)){ }
						if(prop.indexOf(".callback") > -1){result = prop; found = true; 
						} else { deep++;
							for (var p in cur) { recurse(cur[p], prop ? prop + "." + p : p, deep);}
						}
					} catch(ex) { console.log("ERROR in function: " + ex); }
				}

				recurse(___grecaptcha_cfg.clients[0], "", 0);
				found ? "___grecaptcha_cfg.clients[0]." + result : null;
			');
			$this->exts->log('Callback function: '.$gcallbackFunction);
			if($gcallbackFunction != null){
				$this->exts->execute_javascript($gcallbackFunction.'("'.$this->exts->recaptcha_answer.'");');
				sleep(10);
			}
		}
	} else {
		$this->exts->log(__FUNCTION__.'::Not found reCaptcha');
	}
}

public function processRecaptcha($base_url, $google_key = '', $fill_answer = true)
{
	$this->recaptcha_answer = '';
	// if (empty($google_key)) {
	//     / @var WebDriverElement $element /
	//     $element = $this->exts->getElement(".g-recaptcha");
	//     $google_key = $this->exts->getElAttribute($element, 'data-sitekey');
	// }
	
	$this->exts->log("--Google Re-Captcha--");
	if (!empty($this->exts->config_array['recaptcha_shell_script'])) {
		$cmd = $this->exts->config_array['recaptcha_shell_script']." --PROCESS_UID::".$this->exts->process_uid." --GOOGLE_KEY::".urlencode($google_key)." --BASE_URL::".urlencode($base_url);
		$this->exts->log('Executing command : '.$cmd);
		exec($cmd, $output, $return_var);
		$this->exts->log('Command Result : '.print_r($output, true));
		
		if (!empty($output)) {
			$recaptcha_answer = '';
			foreach ($output as $line) {
				if (stripos($line, "RECAPTCHA_ANSWER") !== false) {
					$result_codes = explode("RECAPTCHA_ANSWER:", $line);
					$recaptcha_answer = $result_codes[1];
					break;
				}
			}
			
			if (!empty($recaptcha_answer)) {
				if ($fill_answer) {
					$answer_filled = $this->exts->execute_javascript(
						"document.getElementById(\"g-recaptcha-response\").innerHTML = arguments[0];return document.getElementById(\"g-recaptcha-response\").innerHTML;", [$recaptcha_answer]
					);
					$this->exts->log("recaptcha answer filled - ".$answer_filled);
				}
				
				$this->exts->recaptcha_answer = $recaptcha_answer;
				
				return true;
			}
		}
	}
	
	return false;
}

private function processInvoices() {
	$this->exts->waitTillAnyPresent(['.group:not(.open) button.accept.group-opener', 'a[href*="action=download-invoice"]']);
	$this->exts->capture("4-invoices-page");
	
	$limit = 10;
	if($this->restrictPages == 0)  {
		//This is needed because opening take lots of time.
		$this->exts->update_process_lock();
		$limit = 60;
	}
	for ($count = 1; $count <= $limit && $this->exts->exists('.group:not(.open) button.accept.group-opener'); $count++) {
		$this->exts->log('Expand section ' . trim($this->exts->extract('.group:not(.open) button.accept.group-opener')));
		$this->exts->moveToElementAndClick('.group:not(.open) button.accept.group-opener');
		sleep(5);
	}
	$this->exts->capture("4-invoices-page-expanded");
	$invoices = [];
	$rows = $this->exts->getElements('table > tbody > tr');
	foreach ($rows as $row) {
		$tags = $this->exts->getElements('td', $row);
		if(count($tags) >= 5 && $this->exts->getElement('a[href*="action=download-invoice"]', $tags[4]) != null) {
			$invoiceUrl = $this->exts->getElement('a[href*="action=download-invoice"]', $tags[4])->getAttribute("href");
			$invoiceName = trim($tags[2]->getAttribute('innerText'));
			$invoiceDate = trim($tags[1]->getAttribute('innerText'));
			$invoiceAmount = trim(preg_replace('/[^\d\.\,]/', '', $tags[3]->getAttribute('innerText'))) . ' EUR';
			
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
		
		$downloaded_file = $this->exts->direct_download($invoice['invoiceUrl'], 'pdf', $invoiceFileName);
		if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
			$this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
		} else {
			$this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
		}
	}
}