<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use ubl21dian\Templates\SOAP\GetStatus;
use ubl21dian\Templates\SOAP\GetStatusZip;
use App\Traits\DocumentTrait;
use App\TypeDocument;
use App\Mail\InvoiceMail;
use App\Customer;
use App\Document;
use App\DocumentPayroll;
use App\Resolution;
use App\User;
use App\Company;
use App\TypeLiability;
use ubl21dian\XAdES\SignAttachedDocument;
use App\Http\Requests\Api\StatusRequest;
use Illuminate\Support\Facades\Mail;

class StateController extends Controller
{
    use DocumentTrait;

    /**
     * Zip.
     *
     * @param string $trackId
     *
     * @return array
     */

    public function zip(StatusRequest $request, $trackId, $GuardarEn = false)
    {
        // User
        $user = auth()->user();

        if($user->validate_mail_server()){
            \Config::set('mail.host', $user->mail_host);
            \Config::set('mail.port', $user->mail_port);
            \Config::set('mail.username', $user->mail_username);
            \Config::set('mail.password', $user->mail_password);
            \Config::set('mail.encryption', $user->mail_encryption);
        }

        // User company
        $company = $user->company;

        if($request->is_payroll)
            $getStatusZip = new GetStatusZip($user->company->certificate->path, $user->company->certificate->password, $user->company->software->url_payroll);
        else
            $getStatusZip = new GetStatusZip($user->company->certificate->path, $user->company->certificate->password, $user->company->software->url);
        $getStatusZip->trackId = $trackId;
        $GuardarEn = str_replace("_", "\\", $GuardarEn);

        if ($GuardarEn){
            if (!is_dir($GuardarEn)) {
                mkdir($GuardarEn);
            }
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }

        }

        $respuestadian = '';
        $typeDocument = TypeDocument::findOrFail(7);
        $resolution = NULL;
        $customer = NULL;
//        $xml = new \DOMDocument;
        $ar = new \DOMDocument;
        if ($GuardarEn){
            $respuestadian = $getStatusZip->signToSend($GuardarEn."\\ReqZIP-".$trackId.".xml")->getResponseToObject($GuardarEn."\\RptaZIP-".$trackId.".xml");
            if($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == 'true'){
                if(isset($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName->_attributes))
                {
                    $invoicenumber = $this->InvoiceByZipKey($company->identification_number, $trackId);
                    $signedxml = file_get_contents(storage_path("app/public/{$company->identification_number}/FES-".$invoicenumber));
                }
                else
                    $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName.".xml"));

                if(strpos($signedxml, "</Invoice>") > 0)
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        if(strpos($signedxml, "</DebitNote>") > 0)
                            $td = '/DebitNote';
                        else
                            if(strpos($signedxml, "</NominaIndividual>") > 0)
                                $td = '/NominaIndividual';
                            else
                                if(strpos($signedxml, "</NominaIndividualDeAjuste>") > 0)
                                    $td = '/NominaIndividualDeAjuste';

//                $xml->loadXML($signedxml);

                $filename = str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName))));
                if($request->atacheddocument_name_prefix)
                    $filename = $request->atacheddocument_name_prefix.$filename;

                $cufecude = $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlDocumentKey;

//                if($td == '/NominaIndividual' || $td == 'NominaIndividualDeAjuste')
//                    $cufecude = $this->getTag($signedxml, 'InformacionGeneral', 0, 'CUNE');
//                else
//                    $cufecude = $this->ValueXML($signedxml, $td."/cbc:UUID/");
                $appresponsexml = base64_decode($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlBase64Bytes);
                $ar->loadXML($appresponsexml);
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                    $document_number = $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Numero');
                else
                    $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");

                if($td == '/Invoice')
                    $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                else
                    if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                        $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))->firstOrFail();
                    else
                        $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->firstOrFail();

                $resolution->document_number = $document_number;
                // Create XML AttachedDocument
                $at = '';
                if($td != '/NominaIndividual' && $td != '/NominaIndividualDeAjuste'){
                    $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name/"),
                          'email' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                          'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'address' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                          'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail(),
                          'type_regime_id' => "2"
                         ];
                    $customer = new user($u);
                    $customer->company = new Company($u);
                    $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));
                    // Signature XML
                    $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                    $signAttachedDocument->GuardarEn = $GuardarEn."\\{$filename}.xml";

                    $at = $signAttachedDocument->sign($attacheddocument)->xml;
                    $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
//                    $file = fopen($GuardarEn."\\Attachment-".$this->valueXML($signedxml, $td."/cbc:ID/").".xml", "w");
                    $file = fopen($GuardarEn."\\{$filename}".".xml", "w");
                    fwrite($file, $at);
                    fclose($file);
                    $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"));
                    $invoice = Document::where('identification_number', '=', $company->identification_number)
                                       ->where('customer', '=', $customer->identification_number)
                                       ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                       ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                       ->where('state_document_id', '=', 0)->get();
                    if(count($invoice) > 0){
                        $invoice[0]->state_document_id = 1;
                        $invoice[0]->cufe = $cufecude;
                        $invoice[0]->save();
                    }
                    if(isset($request))
                        if($request->sendmail){
                            $invoice = Document::where('identification_number', '=', $company->identification_number)
                                               ->where('customer', '=', $customer->identification_number)
                                               ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                               ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                               ->where('state_document_id', '=', 1)->get();
                            if(count($invoice) > 0 && $customer->identification_number != '222222222222'){
                                Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename));
                                if($request->senmailtome)
                                    Mail::to($user->email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename));
                                if($request->email_cc_list){
                                    foreach($request->email_cc_list as $email)
                                        Mail::to($email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename));
                                }
                            }
                        }
                }
                else{
                    $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                              ->where('employee_id', '=', $this->getTag($signedxml, 'Trabajador', 0, 'NumeroDocumento'))
                                              ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                              ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                              ->where('state_document_id', '=', 0)->get();
                    if(count($payroll) > 0){
                        $payroll[0]->state_document_id = 1;
                        $payroll[0]->cune = $cufecude;
                        $payroll[0]->save();
                    }
                }
            }
            else
              $at = '';
            return [
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => $respuestadian,
                'reqzip'=>base64_encode(file_get_contents($GuardarEn."\\ReqZIP-{$trackId}.xml")),
                'rptazip'=>base64_encode(file_get_contents($GuardarEn."\\RptaZIP-{$trackId}.xml")),
                'attacheddocument'=>base64_encode($at),
                'cufecude'=>$cufecude,
            ];
        }
        else{
            $respuestadian = $getStatusZip->signToSend(storage_path("app/public/{$company->identification_number}/ReqZIP-".$trackId.".xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaZIP-".$trackId.".xml"));
            if($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->IsValid == 'true'){
                if(isset($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName->_attributes))
                {
                    $invoicenumber = $this->InvoiceByZipKey($company->identification_number, $trackId);
                    $signedxml = file_get_contents(storage_path("app/public/{$company->identification_number}/FES-".$invoicenumber));
                }
                else
                    $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName.".xml"));
//                $xml->loadXML($signedxml);

                if(strpos($signedxml, "</Invoice>") > 0)
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        if(strpos($signedxml, "</DebitNote>") > 0)
                            $td = '/DebitNote';
                        else
                            if(strpos($signedxml, "</NominaIndividual>") > 0)
                                $td = '/NominaIndividual';
                            else
                                if(strpos($signedxml, "</NominaIndividualDeAjuste>") > 0)
                                    $td = '/NominaIndividualDeAjuste';

//                if(isset($respuestadian->Envelope->Body->GetStatusZip;Response->GetStatusZipResult->DianResponse->XmlFileName->_attributes))
//                    $xml = $this->readXML(storage_path("app/public/{$company->identification_number}/FES-".$invoicenumber));
//                else
//                    $xml = $this->readXML(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName.".xml"));

//                return $this->ValueXML($signedxml, '/CreditNote/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:Name/');

                $filename = str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlFileName))));
                if($request->atacheddocument_name_prefix)
                    $filename = $request->atacheddocument_name_prefix.$filename;

                $cufecude = $respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlDocumentKey;

//                if($td == '/NominaIndividual' || $td == 'NominaIndividualDeAjuste')
//                    $cufecude = $this->getTag($signedxml, 'InformacionGeneral', 0, 'CUNE');
//                else
//                    $cufecude = $this->ValueXML($signedxml, $td."/cbc:UUID/");

                $appresponsexml = base64_decode($respuestadian->Envelope->Body->GetStatusZipResponse->GetStatusZipResult->DianResponse->XmlBase64Bytes);
                $ar->loadXML($appresponsexml);
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                    $document_number = $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Numero');
                else
                    $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");

                if($td == '/Invoice')
                    $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                else
                    if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                        $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))->firstOrFail();
                    else
                        $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->firstOrFail();

                $resolution->document_number = $document_number;
                  // Create XML AttachedDocument
                $at = '';
                if($td != '/NominaIndividual' && $td != '/NominaIndividualDeAjuste'){
                    $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name/"),
                          'email' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                          'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'address' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                          'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail(),
                          'type_regime_id' => "2"
                         ];
                    $customer = new user($u);
                    $customer->company = new Company($u);
                    $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));
                    // Signature XML
                    $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                    $signAttachedDocument->GuardarEn = storage_path("app/public/{$company->identification_number}/{$filename}.xml");

                    $at = $signAttachedDocument->sign($attacheddocument)->xml;
                    $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
                    $file = fopen(storage_path("app/public/{$company->identification_number}/{$filename}".".xml"), "w");
                    fwrite($file, $at);
                    fclose($file);
                    $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"));
                    $invoice = Document::where('identification_number', '=', $company->identification_number)
                                       ->where('customer', '=', $customer->identification_number)
                                       ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                       ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                       ->where('state_document_id', '=', 0)->get();
                    if(count($invoice) > 0){
                        $invoice[0]->state_document_id = 1;
                        $invoice[0]->cufe = $cufecude;
                        $invoice[0]->save();
                    }
                    if(isset($request))
                        if($request->sendmail){
                            $invoice = Document::where('identification_number', '=', $company->identification_number)
                                               ->where('customer', '=', $customer->identification_number)
                                               ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                               ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                               ->where('state_document_id', '=', 1)->get();
                            if(count($invoice) > 0 && $customer->identification_number != '222222222222'){
                                Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, TRUE));
                                if($request->sendmailtome)
                                    Mail::to($user->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE));
                                if($request->email_cc_list){
                                    foreach($request->email_cc_list as $email)
                                        Mail::to($email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE));
                                }
                            }
                        }
                }
                else{
                    $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                              ->where('employee_id', '=', $this->getTag($signedxml, 'Trabajador', 0, 'NumeroDocumento'))
                                              ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                              ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                              ->where('state_document_id', '=', 0)->get();
                    if(count($payroll) > 0){
                        $payroll[0]->state_document_id = 1;
                        $payroll[0]->cune = $cufecude;
                        $payroll[0]->save();
                    }
                }
            }
            else
                $at = '';
            return [
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => $respuestadian,
                'reqzip'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqZIP-{$trackId}.xml"))),
                'rptazip'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaZIP-{$trackId}.xml"))),
                'attacheddocument'=>base64_encode($at),
            ];
        }
    }

    /**
     * Document.
     *
     * @param string $trackId
     *
     * @return array
     */
    public function document(StatusRequest $request, $trackId, $GuardarEn = false)
    {
        // User
        $user = auth()->user();

        if($user->validate_mail_server()){
            \Config::set('mail.host', $user->mail_host);
            \Config::set('mail.port', $user->mail_port);
            \Config::set('mail.username', $user->mail_username);
            \Config::set('mail.password', $user->mail_password);
            \Config::set('mail.encryption', $user->mail_encryption);
        }

        $company = $user->company;

        if($request->is_payroll)
            $getStatus = new GetStatus($user->company->certificate->path, $user->company->certificate->password, $user->company->software->url_payroll);
        else
            $getStatus = new GetStatus($user->company->certificate->path, $user->company->certificate->password, $user->company->software->url);
        $getStatus->trackId = $trackId;
        $GuardarEn = str_replace("_", "\\", $GuardarEn);

        if ($GuardarEn){
            if (!is_dir($GuardarEn)) {
                mkdir($GuardarEn);
            }
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
        }

        $respuestadian = '';
        $typeDocument = TypeDocument::findOrFail(7);
        $resolution = NULL;
        $customer = NULL;
        $cufecude = '';
//        $xml = new \DOMDocument;
        $ar = new \DOMDocument;
        if ($GuardarEn){
            $respuestadian = $getStatus->signToSend($GuardarEn."\\ReqZIP-".$trackId.".xml")->getResponseToObject($GuardarEn."\\RptaZIP-".$trackId.".xml");
            if($respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->IsValid == 'true'){
                $filename = str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlFileName))));
                if($request->atacheddocument_name_prefix)
                    $filename = $request->atacheddocument_name_prefix.$filename;

                $cufecude = $respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlDocumentKey;
                $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlFileName.".xml"));
//                $xml->loadXML($signedxml);
                if(strpos($signedxml, "</Invoice>") > 0)
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        if(strpos($signedxml, "</DebitNote>") > 0)
                            $td = '/DebitNote';
                        else
                            if(strpos($signedxml, "</NominaIndividual>") > 0)
                                $td = '/NominaIndividual';
                            else
                                if(strpos($signedxml, "</NominaIndividualDeAjuste>") > 0)
                                    $td = '/NominaIndividualDeAjuste';

                $appresponsexml = base64_decode($respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlBase64Bytes);
                $ar->loadXML($appresponsexml);
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                    $document_number = $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Numero');
                else
                    $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");

                if($td == '/Invoice')
                    $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                else
                    if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                        $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))->firstOrFail();
                    else
                        $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->firstOrFail();

                $resolution->document_number = $document_number;
                // Create XML AttachedDocument
                $at = '';
                if($td != '/NominaIndividual' && $td != '/NominaIndividualDeAjuste'){
                    $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name/"),
                          'email' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                          'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'address' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                          'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail(),
                          'type_regime_id' => "2"
                         ];
                    $customer = new user($u);
                    $customer->company = new Company($u);
                    $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));
                    // Signature XML
                    $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                    $signAttachedDocument->GuardarEn = $GuardarEn."\\{$filename}.xml";

                    $at = $signAttachedDocument->sign($attacheddocument)->xml;
                    $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
                    $file = fopen($GuardarEn."\\{$filename}".".xml", "w");
//                    $file = fopen($GuardarEn."\\Attachment-".$this->valueXML($signedxml, $td."/cbc:ID/").".xml", "w");
                    fwrite($file, $at);
                    fclose($file);
                    $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"));
                    $invoice = Document::where('identification_number', '=', $company->identification_number)
                                       ->where('customer', '=', $customer->identification_number)
                                       ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                       ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                       ->where('state_document_id', '=', 0)->get();
                    if(count($invoice) > 0){
                        $invoice[0]->state_document_id = 1;
                        $invoice[0]->cufe = $cufecude;
                        $invoice[0]->save();
                    }
                    if(isset($request))
                        if($request->sendmail){
                            $invoice = Document::where('identification_number', '=', $company->identification_number)
                                               ->where('customer', '=', $customer->identification_number)
                                               ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                               ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                               ->where('state_document_id', '=', 1)->get();
                            if(count($invoice) > 0 && $customer->company->identification_number != '222222222222'){
                                Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename, TRUE, $request));
                                if($request->sendmailtome)
                                    Mail::to($user->email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename, FALSE, $request));
                                if($request->email_cc_list){
                                    foreach($request->email_cc_list as $email)
                                        Mail::to($email)->send(new InvoiceMail($invoice, $customer, $company, $GuardarEn, FALSE, FALSE, $filename, FALSE, $request));
                                }
                            }
                        }
                }
                else{
                    $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                              ->where('employee_id', '=', $this->getTag($signedxml, 'Trabajador', 0, 'NumeroDocumento'))
                                              ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                              ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                              ->where('state_document_id', '=', 0)->get();
                    if(count($payroll) > 0){
                        $payroll[0]->state_document_id = 1;
                        $payroll[0]->cune = $cufecude;
                        $payroll[0]->save();
                    }
                }
            }
            else
              $at = '';
            return [
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => $respuestadian,
                'reqzip'=>base64_encode(file_get_contents($GuardarEn."\\ReqZIP-{$trackId}.xml")),
                'rptazip'=>base64_encode(file_get_contents($GuardarEn."\\RptaZIP-{$trackId}.xml")),
                'attacheddocument'=>base64_encode($at),
                'cufecude'=>$cufecude,
            ];
        }
        else{
            $respuestadian = $getStatus->signToSend(storage_path("app/public/{$company->identification_number}/ReqZIP-".$trackId.".xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaZIP-".$trackId.".xml"));
            if($respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->IsValid == 'true'){
                $filename = str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlFileName))));
                if($request->atacheddocument_name_prefix)
                    $filename = $request->atacheddocument_name_prefix.$filename;
                $cufecude = $respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlDocumentKey;
                $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlFileName.".xml"));
                if(strpos($signedxml, "</Invoice>") > 0)
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        if(strpos($signedxml, "</DebitNote>") > 0)
                            $td = '/DebitNote';
                        else
                            if(strpos($signedxml, "</NominaIndividual>") > 0)
                                $td = '/NominaIndividual';
                            else
                                if(strpos($signedxml, "</NominaIndividualDeAjuste>") > 0)
                                    $td = '/NominaIndividualDeAjuste';

//                $xml->loadXML($signedxml);
                $appresponsexml = base64_decode($respuestadian->Envelope->Body->GetStatusResponse->GetStatusResult->XmlBase64Bytes);
                $ar->loadXML($appresponsexml);
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                    $document_number = $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Numero');
                else
                    $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");

                if($td == '/Invoice')
                    $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->where('resolution', $this->getTag($signedxml, 'InvoiceAuthorization', 0)->nodeValue)->firstOrFail();
                else
                    if($td == '/NominaIndividual' || $td == '/NominaIndividualDeAjuste')
                        $resolution = Resolution::where('prefix', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))->firstOrFail();
                    else
                        $resolution = Resolution::where('prefix', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))->firstOrFail();

                $resolution->document_number = $document_number;
                // Create XML AttachedDocument
                $at = '';
                if($td != '/NominaIndividual' && $td != '/NominaIndividualDeAjuste'){
                    $u = ['name' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name/"),
                          'email' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'identification_number' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"),
                          'phone' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:Contact/cbc:ElectronicMail/"),
                          'address' => $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cac:RegistrationAddress/cac:AddressLine/cbc:Line/"),
                          'type_liability_id' => TypeLiability::where('code', $this->ValueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyTaxScheme/cbc:TaxLevelCode/"))->firstOrFail(),
                          'type_regime_id' => "2"
                         ];
                    $customer = new user($u);
                    $customer->company = new Company($u);
                    $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));
                    // Signature XML
                    $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                    $signAttachedDocument->GuardarEn = storage_path("app/public/{$company->identification_number}/{$filename}.xml");

                    $at = $signAttachedDocument->sign($attacheddocument)->xml;
                    $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
                    $file = fopen(storage_path("app/public/{$company->identification_number}/{$filename}".".xml"), "w");
//                    $file = fopen(storage_path("app/public/{$company->identification_number}/Attachment-".$this->valueXML($signedxml, $td."/cbc:ID/").".xml"), "w");
                    fwrite($file, $at);
                    fclose($file);
                    $customer = Customer::findOrFail($this->valueXML($signedxml, $td."/cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:CompanyID/"));
                    $invoice = Document::where('identification_number', '=', $company->identification_number)
                                       ->where('customer', '=', $customer->identification_number)
                                       ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                       ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                       ->where('state_document_id', '=', 0)->get();
                    if(count($invoice) > 0){
                        $invoice[0]->state_document_id = 1;
                        $invoice[0]->cufe = $cufecude;
                        $invoice[0]->save();
                    }
                    if(isset($request))
                        if($request->sendmail){
                            $invoice = Document::where('identification_number', '=', $company->identification_number)
                                               ->where('customer', '=', $customer->identification_number)
                                               ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                               ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                               ->where('state_document_id', '=', 1)->get();
                            if(count($invoice) > 0 && $customer->identification_number != '222222222222'){
                                Mail::to($customer->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, TRUE, $request));
                                if($request->sendmailtome)
                                    Mail::to($user->email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
                                if($request->email_cc_list){
                                    foreach($request->email_cc_list as $email)
                                        Mail::to($email)->send(new InvoiceMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
                                }
                            }
                        }
                }
                else{
                    $payroll = DocumentPayroll::where('identification_number', '=', $company->identification_number)
                                              ->where('employee_id', '=', $this->getTag($signedxml, 'Trabajador', 0, 'NumeroDocumento'))
                                              ->where('prefix', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Prefijo'))
                                              ->where('consecutive', '=', $this->getTag($signedxml, 'NumeroSecuenciaXML', 0, 'Consecutivo'))
                                              ->where('state_document_id', '=', 0)->get();
                    if(count($payroll) > 0){
                        $payroll[0]->state_document_id = 1;
                        $payroll[0]->cune = $cufecude;
                        $payroll[0]->save();
                    }
                }
            }
            else
              $at = '';
            return [
                'message' => 'Consulta generada con éxito',
                'ResponseDian' => $respuestadian,
                'reqzip'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqZIP-{$trackId}.xml"))),
                'rptazip'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaZIP-{$trackId}.xml"))),
                'attacheddocument'=>base64_encode($at),
                'cufecude'=>$cufecude,
            ];
        }
    }
}
