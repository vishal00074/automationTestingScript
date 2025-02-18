<?php // updated login code
// Server-Portal-ID: 68674 - Last modified: 16.12.2024 13:48:39 UTC - User: 1

public $baseUrl = 'https://totalcardsonline.total.de';
public $loginUrl = 'https://totalcardsonline.total.de/secure/clients/factures/recherche.do';
public $invoicePageUrl = 'https://totalcardsonline.total.de/secure/clients/factures/recherche.do';

public $username_selector = ' input[id="fixed-username"],input[name="loginID"],input#loginRem';
public $password_selector = 'form#gigya-password-auth-method-form input[type="password"],input[name="j_password"]';
public $remember_me_selector = '';
public $submit_login_selector = 'input[id="passwd-submit"][value="Submit"], input[id="submitLoginPasswordLess"],div[name="checkLoginId"],div#okbtn';

public $check_login_failed_selector = 'div#connexionErrorDiv';
public $check_login_success_selector = 'span[class*="user-profile-img"],div[onclick*="logout.do"]';

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
    $this->exts->capture('1-init-page');

    // If user hase not logged in from cookie, clear cookie, open the login url and do login
    if($this->exts->getElement($this->check_login_success_selector) == null) {
        $this->exts->log('NOT logged via cookie');
        $this->exts->clearCookies();
        $this->exts->openUrl($this->loginUrl);
        sleep(15);
        $this->exts->log('checkFillLogin : 0');
        $this->checkFillLogin();
        sleep(20);

        if($this->exts->exists('input[type="submit"][value="S\'authentifier"]')){
            $this->exts->click_by_xdotool('input[type="submit"][value="S\'authentifier"]');
            sleep(10);

            $this->exts->log('checkFillLogin : 1');
            $this->checkFillLogin();
            sleep(20);
        }

    }

    if($this->exts->exists('form[action*="migrergigya"] div[name="postpone"]')){
        $this->exts->click_by_xdotool('form[action*="migrergigya"] div[name="postpone"]');
        sleep(15);
    }

    
    if($this->exts->getElement($this->check_login_success_selector) != null) {
        sleep(3);
        $this->exts->log(__FUNCTION__.'::User logged in');
        $this->exts->capture("3-login-success");

        // Open invoices url and download invoice
        

        if($this->exts->urlContains('fleet.circlek')){

            $this->exts->openUrl('https://fleet.circlek-deutschland.de/secure/clients/factures/recherche.do');

                $this->invoicePage();

        }else{
            $this->exts->moveToElementAndClick('button[class="wm-visual-design-button"]:has(svg)');
            sleep(10);
            if($this->exts->exists('a span[class*="page-suppliersandinvoices"]')){
                $this->exts->click_by_xdotool('a span[class*="page-suppliersandinvoices"]');
                sleep(5);
                $this->exts->click_by_xdotool('div#grana-submenu-container  a[class*="page-suppliersandinvoices_mytotalinvoices"]');
            }

            $this->invoicePage();
        }
        
        
        
        // Final, check no invoice
        if($this->isNoInvoice){
            $this->exts->no_invoice();
        }
        $this->exts->success();
    } else {
        $this->exts->log(__FUNCTION__.'::Use login failed ' . $this->exts->getUrl());
        $this->exts->log($this->exts->extract('form[action*="migrergigya"]', null, 'innerText'));
        if($this->exts->getElement($this->check_login_failed_selector) != null) {
            $this->exts->loginFailure(1);
        } else if (strpos(strtolower($this->exts->extract('form[action*="migrergigya"]', null, 'innerText')), 'sie von der alten benutzer-id auf ihre') !== false && strpos(strtolower($this->exts->extract('form[action*="migrergigya"]', null, 'innerText')), 'e-mailadresse umstellen') !== false) {
            $this->exts->loginFailure(1);
        } else {
            $this->exts->loginFailure();
        }
    }
}

private function checkFillLogin() {
    if($this->exts->getElement($this->username_selector) != null) {
        sleep(3);
        $this->exts->capture("2-login-page");

        $this->exts->log("Enter Username");
        $this->exts->moveToElementAndType($this->username_selector, $this->username);
        sleep(1);

        $this->exts->waitTillPresent($this->submit_login_selector, 15);

        if(!$this->exts->exists($this->password_selector) && $this->exts->exists($this->submit_login_selector) ){
            $this->exts->click_by_xdotool($this->submit_login_selector);
            sleep(5);
        }


        if($this->exts->urlContains('gig_login_hint')){
            sleep(15);
        }else{
            $this->exts->waitTillPresent($this->password_selector, 15); 
        }
        

        $this->exts->moveToElementAndType($this->password_selector, $this->password);
        sleep(30);


        //if($this->remember_me_selector != '')
            // $this->exts->click_by_xdotool($this->remember_me_selector);
        //sleep(5);

        $this->exts->waitTillPresent($this->submit_login_selector, 15);
        $this->exts->capture("2-login-page-filled");
        $this->exts->click_by_xdotool($this->submit_login_selector);
        sleep(10);

        $this->findLoggedPage();

        if($this->exts->exists('.gigya-composite-control.gigya-composite-control-submit input[id="passwd-submit"]') ){
            $this->exts->click_by_xdotool('.gigya-composite-control.gigya-composite-control-submit input[id="passwd-submit"]');
            sleep(5);
        }

        
    } else {
        $this->exts->log(__FUNCTION__.'::Login page not found');
        $this->exts->capture("2-login-page-not-found");
    }
}

private function findLoggedPage()
{
    $timeout = 200; // Max wait time in seconds
    $interval = 5;  // Time to wait between checks (adjust as needed)
    $startTime = time();
    $this->exts->log("Finding check_login_success_selector ");

    while (time() - $startTime < $timeout) {
        if ($this->exts->exists($this->exts->exists($this->check_login_success_selector))) {
            $this->exts->log("check_login_success_selector Found");
            break;
        }
        $this->exts->click_by_xdotool($this->submit_login_selector);
        $this->exts->waitTillPresent($this->check_login_success_selector, 10);
        sleep($interval); 
    }

    // Optional: Handle case where the element was not found within 200 seconds
    if (!$this->exts->exists($this->check_login_success_selector)) {
          $this->exts->log("Element not found within 200 seconds.");
    }
}

private function invoicePage(){
    sleep(20);
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    if($restrictPages == 0){
        $startDate = date('d/m/Y', strtotime('-3 years'));
    } else {
        $startDate = date('d/m/Y', strtotime('-1 years'));
    }
    

    if($this->exts->urlContains('fleet.circlek')){
        $this->exts->moveToElementAndType('input[name="dateDebutPeriodeFacturation"]', '');
        sleep(3);
        $this->exts->moveToElementAndType('input[name="dateDebutPeriodeFacturation"]', $startDate);
        sleep(3);
        $endDate = date('d/m/Y');
        $this->exts->moveToElementAndType('input[name="dateFinPeriodeFacturation"]', '');
        sleep(3);
        $this->exts->moveToElementAndType('input[name="dateFinPeriodeFacturation"]', $endDate);
        sleep(3);
        $this->exts->click_by_xdotool('div[name=rechercher]');
        sleep(5);
        $this->processInvoiceslatest();
    } else {
        sleep(5);
        $this->exts->click_by_xdotool('div#invoiceFilterDIV-tableActivefilters a[filtername="billingDate"]');
        $this->processInvoices(); 
    }
}

private function processInvoices($paging_count=1) {
    sleep(25);
    

    $this->exts->capture("4-invoices-page");
    $invoices = [];

    $rows = $this->exts->getElements('table > tbody > tr');
    foreach ($rows as $row) {
        $tags = $this->exts->getElements('td', $row);
        if(count($tags) >= 12 ) {
            //$invoiceUrl = $this->exts->getElement('a[class="downloadEViewingDoc"]', $tags[11])->getAttribute("doc-url");
            $invoiceName = trim($tags[4]->getAttribute('innerText'));
            $invoiceDate = trim($tags[3]->getAttribute('innerText'));
            $invoiceAmount = '';

        //  array_push($invoices, array(
        //      'invoiceName'=>$invoiceName,
        //      'invoiceDate'=>$invoiceDate,
        //      'invoiceAmount'=>$invoiceAmount,
        //      'invoiceUrl'=>$invoiceUrl
        //  ));
        
        // }


        $this->isNoInvoice = false;

        //$invoiceFileName = $invoiceName.'.zip';
        $invoiceDate = $this->exts->parse_date($invoiceDate, 'd/m/Y','Y-m-d');
        $invoiceFileName = $invoiceDate.'.zip';
        $invoiceFilePdfNmae = $invoiceDate.'.pdf';
        $this->exts->log('Date parsed: '.$invoiceDate);
        //$this->exts->open_new_window();
        sleep(2);
        //$this->exts->openUrl($invoice['invoiceUrl']);
        //sleep(8);
        if($this->exts->invoice_exists($invoiceName)){
            $this->exts->log('Invoice existed '.$invoiceFileName);
        } else {
            $this->exts->click_by_xdotool('a[class="downloadEViewingDoc"]');
            sleep(5);
            $this->exts->wait_and_check_download('zip');
            $downloaded_file = $this->exts->find_saved_file('zip', $invoiceFileName);
            sleep(35);
            if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                //$this->exts->new_invoice($invoiceName, $invoiceDate, $invoiceAmount, $invoiceFileName);
                $this->extract_zip_save_pdf($downloaded_file, $invoiceFilePdfNmae);
            } else {
                $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
            }
        }
    }

    }

    
    
    $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
    $paging_count++;
    if($restrictPages == 0 &&
        $paging_count < 50 &&
        $this->exts->getElement('[name="anchorRecherche"] ~ a[href*="/factures/resultat.do?method=rafraichir"][href*="-p='.$paging_count.'"]') != null
    ){
        
        $this->exts->click_by_xdotool('[name="anchorRecherche"] ~ a[href*="/factures/resultat.do?method=rafraichir"][href*="-p='.$paging_count.'"]');
        sleep(5);
        $this->processInvoices($paging_count);
    }
}

function extract_zip_save_pdf($zipfile, $invoiceFileName) {
    $zip = new \ZipArchive;
    $res = $zip->open($zipfile);

    if ($res === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipFileStat = $zip->statIndex($i);
            $fileName = $zipFileStat['name'];
            $fileInfo = pathinfo($fileName);

            // Define the full path for extraction
            $downloadFolder = $this->exts->config_array['download_folder'];
            $extractPath = $downloadFolder . $invoiceFileName;

            // Check if it's a PDF file
            if (isset($fileInfo['extension']) && strtolower($fileInfo['extension']) === 'pdf') {
                $this->isNoInvoice = false;

                // Rename extracted PDF to use invoiceFileName
                $zip->extractTo($downloadFolder, [$fileName]);
                $originalPath = $downloadFolder . $fileName;
                rename($originalPath, $extractPath);

                $this->exts->new_invoice($fileInfo['filename'], "", "", $extractPath);
                $this->exts->log("Extracted PDF renamed to: $extractPath");
                sleep(1);
            }

            // Check if it's a ZIP file (nested ZIP)
            elseif (isset($fileInfo['extension']) && strtolower($fileInfo['extension']) === 'zip') {
                $nestedZipPath = $downloadFolder . $fileName;
                $zip->extractTo($downloadFolder, [$fileName]);

                $this->exts->log("Extracted nested ZIP: $nestedZipPath");

                // Recursively process the nested ZIP
                $this->extract_zip_save_pdf($nestedZipPath, $invoiceFileName);
            }
        }

        $zip->close();
        unlink($zipfile); // Delete the original ZIP file
    } else {
        $this->exts->log(__FUNCTION__ . '::File extraction failed');
    }
}

private function processInvoiceslatest($paging_count=1) {
        sleep(25);
        
        $this->exts->capture("4-invoices-page");
        $invoices = [];

        $rows = $this->exts->getElements('table > tbody > tr');
        foreach ($rows as $row) {
            $tags = $this->exts->getElements('td', $row);
            if(count($tags) >= 8 && $this->exts->getElement('a[href*="method=traite"]', $tags[7]) != null) {
                $invoiceUrl = $this->exts->getElement('a[href*="method=traite"]', $tags[7])->getAttribute("href");
                $invoiceName = trim($tags[2]->getAttribute('innerText'));
                $invoiceDate = trim($tags[1]->getAttribute('innerText'));
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

            $invoiceFileName = $invoice['invoiceName'].'.zip';
            $invoice['invoiceDate'] = $this->exts->parse_date($invoice['invoiceDate'], 'd/m/Y','Y-m-d');
            $this->exts->log('Date parsed: '.$invoice['invoiceDate']);
            // $this->exts->open_new_window();
            sleep(2);
            $this->exts->openUrl($invoice['invoiceUrl']);
            sleep(8);
            if($this->exts->invoice_exists($invoice['invoiceName'])){
                $this->exts->log('Invoice existed '.$invoiceFileName);
            } else {
                $this->exts->click_by_xdotool('div#telecharger');
                sleep(5);
                $this->exts->wait_and_check_download('zip');
                $downloaded_file = $this->exts->find_saved_file('zip', $invoiceFileName);

                if(trim($downloaded_file) != '' && file_exists($downloaded_file)){
                    $this->extract_single_zip_save_pdf($downloaded_file);
                    // $this->exts->new_invoice($invoice['invoiceName'], $invoice['invoiceDate'], $invoice['invoiceAmount'], $invoiceFileName);
                } else {
                    $this->exts->log(__FUNCTION__.'::No download '.$invoiceFileName);
                }
            }
            // $this->exts->close_new_window();
            sleep(1);
        }
        
        $restrictPages = isset($this->exts->config_array["restrictPages"]) ? (int)@$this->exts->config_array["restrictPages"] : 3;
        $paging_count++;
        if($restrictPages == 0 &&
            $paging_count < 50 &&
            $this->exts->getElement('[name="anchorRecherche"] ~ a[href*="/factures/resultat.do?method=rafraichir"][href*="-p='.$paging_count.'"]') != null
        ){
            
            $this->exts->click_by_xdotool('[name="anchorRecherche"] ~ a[href*="/factures/resultat.do?method=rafraichir"][href*="-p='.$paging_count.'"]');
            sleep(5);
            $this->processInvoiceslatest($paging_count);
        }
    }


    function extract_single_zip_save_pdf($zipfile) {
    $zip = new \ZipArchive;
    $res = $zip->open($zipfile);
    if ($res === TRUE) {
        for($i = 0; $i < $zip->numFiles; $i++) {
            $zipPdfFile = $zip->statIndex($i);
            $fileName = basename($zipPdfFile['name']);
            $fileInfo = pathinfo($fileName);
            if($fileInfo['extension'] === 'pdf') {
                $this->isNoInvoice = false;
                $zip->extractTo($this->exts->config_array['download_folder'], array(basename($zipPdfFile['name'])));
                $saved_file = $this->exts->config_array['download_folder'].basename($zipPdfFile['name']);
                $this->exts->new_invoice($fileInfo['filename'], "","", $saved_file);
                sleep(1);
            }
        }
        $zip->close();
        unlink($zipfile);
    } else {
        $this->exts->log(__FUNCTION__.'::File extraction failed');
    }
}