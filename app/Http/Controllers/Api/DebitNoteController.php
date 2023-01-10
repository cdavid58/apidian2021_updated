<?php

namespace App\Http\Controllers\Api;

use App\User;
use App\Company;
use App\TaxTotal;
use App\PaymentForm;
use App\TypeDocument;
use App\TypeCurrency;
use App\TypeOperation;
use App\PaymentMethod;
use App\Municipality;
use App\OrderReference;
use App\BillingReference;
use App\LegalMonetaryTotal;
use App\Document;
use App\HealthField;
use Illuminate\Http\Request;
use App\Traits\DocumentTrait;
use App\Http\Controllers\Controller;
use App\InvoiceLine as DebitNoteLine;
use App\Http\Requests\Api\DebitNoteRequest;
use ubl21dian\XAdES\SignDebitNote;
use ubl21dian\XAdES\SignAttachedDocument;
use ubl21dian\Templates\SOAP\SendBillAsync;
use ubl21dian\Templates\SOAP\SendBillSync;
use ubl21dian\Templates\SOAP\SendTestSetAsync;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use App\AllowanceCharge;
use Carbon\Carbon;


class DebitNoteController extends Controller
{
    use DocumentTrait;

    /**
     * Store.
     *
     * @param \App\Http\Requests\Api\DebitNoteRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(DebitNoteRequest $request)
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

        // Actualizar Tablas

        $this->ActualizarTablas();

        //Document
        $invoice_doc = new Document();
        $invoice_doc->request_api = json_encode($request->all());
        $invoice_doc->state_document_id = 0;
        $invoice_doc->type_document_id = $request->type_document_id;
        $invoice_doc->number = $request->number;
        $invoice_doc->client_id = 1;
        $invoice_doc->client =  $request->customer ;
        $invoice_doc->currency_id = 35;
        $invoice_doc->date_issue = date("Y-m-d H:i:s");
        $invoice_doc->sale = 1000;
        $invoice_doc->total_discount = 100;
        $invoice_doc->taxes =  $request->tax_totals;
        $invoice_doc->total_tax = 150;
        $invoice_doc->subtotal = 800;
        $invoice_doc->total = 1200;
        $invoice_doc->version_ubl_id = 1;
        $invoice_doc->ambient_id = 1;
        $invoice_doc->identification_number = $company->identification_number;
//        $invoice_doc->save();

        // Type document
        $typeDocument = TypeDocument::findOrFail($request->type_document_id);

        // Customer
        $customerAll = collect($request->customer);
        if(isset($customerAll['municipality_id_fact']))
            $customerAll['municipality_id'] = Municipality::where('codefacturador', $customerAll['municipality_id_fact'])->first();
        $customer = new User($customerAll->toArray());

        // Customer company
        $customer->company = new Company($customerAll->toArray());

        // Type operation id
        if(!$request->type_operation_id)
            $request->type_operation_id = 6;
        $typeoperation = TypeOperation::findOrFail($request->type_operation_id);

        // Currency id
        if(isset($request->idcurrency) and (!is_null($request->idcurrency))){
            $idcurrency = TypeCurrency::findOrFail($request->idcurrency);
            $calculationrate = $request->calculationrate;
            $calculationratedate = $request->calculationratedate;
        }
        else{
            $idcurrency = TypeCurrency::findOrFail($invoice_doc->currency_id);
            $calculationrate = 1;
            $calculationratedate = Carbon::now()->format('Y-m-d');
        }

        // Resolution
        $request->resolution->number = $request->number;
        $resolution = $request->resolution;

        // Date time
        $date = $request->date;
        $time = $request->time;

        // Notes
        $notes = $request->notes;

        // Order Reference
        if($request->order_reference)
            $orderreference = new OrderReference($request->order_reference);
        else
            $orderreference = NULL;

        // Health Fields
        if($request->health_fields)
            $healthfields = new HealthField($request->health_fields);
        else
            $healthfields = NULL;

        // Discrepancy response
        $discrepancycode = $request->discrepancyresponsecode;
        $discrepancydescription = $request->discrepancyresponsedescription;

        // Payment form default
        $paymentFormAll = (object) array_merge($this->paymentFormDefault, $request->payment_form ?? []);
        $paymentForm = PaymentForm::findOrFail($paymentFormAll->payment_form_id);
        $paymentForm->payment_method_code = PaymentMethod::findOrFail($paymentFormAll->payment_method_id)->code;
        $paymentForm->payment_due_date = $paymentFormAll->payment_due_date ?? null;
        $paymentForm->duration_measure = $paymentFormAll->duration_measure ?? null;

        // Allowance charges
        $allowanceCharges = collect();
        foreach ($request->allowance_charges ?? [] as $allowanceCharge) {
            $allowanceCharges->push(new AllowanceCharge($allowanceCharge));
        }

        // Tax totals
        $taxTotals = collect();
        foreach ($request->tax_totals ?? [] as $taxTotal) {
            $taxTotals->push(new TaxTotal($taxTotal));
        }

        // Retenciones globales
        $withHoldingTaxTotal = collect();
//        $withHoldingTaxTotalCount = 0;
//        $holdingTaxTotal = $request->holding_tax_total;
        foreach($request->with_holding_tax_total ?? [] as $item) {
//            $withHoldingTaxTotalCount++;
//            $holdingTaxTotal = $request->holding_tax_total;
            $withHoldingTaxTotal->push(new TaxTotal($item));
        }

        // Requested monetary totals
        $requestedMonetaryTotals = new LegalMonetaryTotal($request->requested_monetary_totals);

        // Credit note lines
        $debitNoteLines = collect();
        foreach ($request->debit_note_lines as $debitNoteLine) {
            $debitNoteLines->push(new DebitNoteLine($debitNoteLine));
        }

        // Billing reference
        $billingReference = new BillingReference($request->billing_reference);

        // Create XML
        $debitNote = $this->createXML(compact('user', 'company', 'customer', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'debitNoteLines', 'allowanceCharges', 'requestedMonetaryTotals', 'billingReference', 'date', 'time', 'notes', 'typeoperation', 'orderreference', 'discrepancycode', 'discrepancydescription', 'request', 'idcurrency', 'calculationrate', 'calculationratedate', 'healthfields'));

        // Register Customer
        if(env('APPLY_SEND_CUSTOMER_CREDENTIALS', TRUE))
            $this->registerCustomer($customer, $request->sendmail);
        else
            $this->registerCustomer($customer, $request->send_customer_credentials);

        // Signature XML
        $signDebitNote = new SignDebitNote($company->certificate->path, $company->certificate->password);
        $signDebitNote->softwareID = $company->software->identifier;
        $signDebitNote->pin = $company->software->pin;

        if ($request->GuardarEn){
            if (!is_dir($request->GuardarEn)) {
                mkdir($request->GuardarEn);
            }
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
        }

        if ($request->GuardarEn)
            $signDebitNote->GuardarEn = $request->GuardarEn."\\ND-{$resolution->next_consecutive}.xml";
        else
            $signDebitNote->GuardarEn = storage_path("app/public/{$company->identification_number}/ND-{$resolution->next_consecutive}.xml");

        $sendBillSync = new SendBillSync($company->certificate->path, $company->certificate->password);
        $sendBillSync->To = $company->software->url;
        $sendBillSync->fileName = "{$resolution->next_consecutive}.xml";
        if ($request->GuardarEn)
            $sendBillSync->contentFile = $this->zipBase64($company, $resolution, $signDebitNote->sign($debitNote), $request->GuardarEn."\\NDS-{$resolution->next_consecutive}");
        else
            $sendBillSync->contentFile = $this->zipBase64($company, $resolution, $signDebitNote->sign($debitNote), storage_path("app/public/{$company->identification_number}/NDS-{$resolution->next_consecutive}"));

        $QRStr = $this->createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signDebitNote->ConsultarCUDE(), "ND", $withHoldingTaxTotal, $notes, $healthfields);

        $invoice_doc->prefix = $resolution->prefix;
        $invoice_doc->customer = $customer->company->identification_number;
        $invoice_doc->xml = "NDS-{$resolution->next_consecutive}.xml";
        $invoice_doc->pdf = "NDS-{$resolution->next_consecutive}.pdf";
        $invoice_doc->client_id = $customer->company->identification_number;
        $invoice_doc->client =  $request->customer ;
        if(property_exists($request, 'id_currency'))
            $invoice_doc->currency_id = $request->id_currency;
        else
            $invoice_doc->currency_id = 35;
        $invoice_doc->date_issue = date("Y-m-d H:i:s");
        $invoice_doc->sale = $requestedMonetaryTotals->payable_amount;
        $invoice_doc->total_discount = $requestedMonetaryTotals->allowance_total_amount ?? 0;
        $invoice_doc->taxes =  $request->tax_totals;
        $invoice_doc->total_tax = $requestedMonetaryTotals->tax_inclusive_amount - $requestedMonetaryTotals->tax_exclusive_amount;
        $invoice_doc->subtotal = $requestedMonetaryTotals->line_extension_amount;
        $invoice_doc->total = $requestedMonetaryTotals->payable_amount;
        $invoice_doc->version_ubl_id = 2;
        $invoice_doc->ambient_id = $company->type_environment_id;
        $invoice_doc->identification_number = $company->identification_number;
        $invoice_doc->save();

        $filename = '';
        $respuestadian = '';
        $typeDocument = TypeDocument::findOrFail(7);
//        $xml = new \DOMDocument;
        $ar = new \DOMDocument;
        if ($request->GuardarEn){
            $respuestadian = $sendBillSync->signToSend($request->GuardarEn."\\ReqND-{$resolution->next_consecutive}.xml")->getResponseToObject($request->GuardarEn."\\RptaND-{$resolution->next_consecutive}.xml");
            if($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'true'){
                $filename = str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName)));
                if($request->atacheddocument_name_prefix)
                    $filename = $request->atacheddocument_name_prefix.$filename;
                $cufecude = $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlDocumentKey;
                $invoice_doc->state_document_id = 1;
                $invoice_doc->cufe = $cufecude;
                $invoice_doc->save();
                $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName.".xml"));
//                $xml->loadXML($signedxml);
                if(strpos($signedxml, "</Invoice>") > 0)
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        $td = '/DebitNote';
                $appresponsexml = base64_decode($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlBase64Bytes);
                $ar->loadXML($appresponsexml);
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");
                // Create XML AttachedDocument
                $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));
                // Signature XML
                $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                $signAttachedDocument->GuardarEn = $GuardarEn."\\{$filename}.xml";

                $at = $signAttachedDocument->sign($attacheddocument)->xml;
                $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
                $file = fopen($GuardarEn."\\{$filename}".".xml", "w");
//                $file = fopen($GuardarEn."\\Attachment-".$this->valueXML($signedxml, $td."/cbc:ID/").".xml", "w");
                fwrite($file, $at);
                fclose($file);
                if(isset($request->sendmail)){
                    if($request->sendmail){
                        $invoice = Document::where('identification_number', '=', $company->identification_number)
                                       ->where('customer', '=', $customer->company->identification_number)
                                       ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                       ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                       ->where('state_document_id', '=', 1)->get();
                        if(count($invoice) > 0){
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
            }
            else
              $at = '';
            return [
                'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
                'ResponseDian' => $respuestadian,
                'invoicexml'=>base64_encode(file_get_contents($request->GuardarEn."\\NDS-{$resolution->next_consecutive}.xml")),
                'zipinvoicexml'=>base64_encode(file_get_contents($request->GuardarEn."\\NDS-{$resolution->next_consecutive}.zip")),
                'unsignedinvoicexml'=>base64_encode(file_get_contents($request->GuardarEn."\\ND-{$resolution->next_consecutive}.xml")),
                'reqfe'=>base64_encode(file_get_contents($request->GuardarEn."\\ReqND-{$resolution->next_consecutive}.xml")),
                'rptafe'=>base64_encode(file_get_contents($request->GuardarEn."\\RptaND-{$resolution->next_consecutive}.xml")),
                'attacheddocument'=>base64_encode($at),
                'urlinvoicexml'=>"NDS-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"NDS-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"{$filename}.xml",
                'cude' => $signDebitNote->ConsultarCUDE(),
                'QRStr' => $QRStr
            ];
        }
        else{
            $respuestadian = $sendBillSync->signToSend(storage_path("app/public/{$company->identification_number}/ReqND-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaND-{$resolution->next_consecutive}.xml"));
            if($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->IsValid == 'true'){
                $filename = str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName)));
                if($request->atacheddocument_name_prefix)
                    $filename = $request->atacheddocument_name_prefix.$filename;
                $cufecude = $respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlDocumentKey;
                $invoice_doc->state_document_id = 1;
                $invoice_doc->cufe = $cufecude;
                $invoice_doc->save();
                $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlFileName.".xml"));
//                $xml->loadXML($signedxml);
                if(strpos($signedxml, "</Invoice>") > 0)
                    $td = '/Invoice';
                else
                    if(strpos($signedxml, "</CreditNote>") > 0)
                        $td = '/CreditNote';
                    else
                        $td = '/DebitNote';
                $appresponsexml = base64_decode($respuestadian->Envelope->Body->SendBillSyncResponse->SendBillSyncResult->XmlBase64Bytes);
                $ar->loadXML($appresponsexml);
                $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
                $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
                $document_number = $this->ValueXML($signedxml, $td."/cbc:ID/");
                // Create XML AttachedDocument
                $attacheddocument = $this->createXML(compact('user', 'company', 'customer', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion', 'document_number'));
                // Signature XML
                $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
                $signAttachedDocument->GuardarEn = storage_path("app/public/{$company->identification_number}/{$filename}.xml");

                $at = $signAttachedDocument->sign($attacheddocument)->xml;
                $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
                $file = fopen(storage_path("app/public/{$company->identification_number}/{$filename}".".xml"), "w");
//                $file = fopen(storage_path("app/public/{$company->identification_number}/Attachment-".$this->valueXML($signedxml, $td."/cbc:ID/").".xml"), "w");
                fwrite($file, $at);
                fclose($file);
                if(isset($request->sendmail)){
                    if($request->sendmail){
                        $invoice = Document::where('identification_number', '=', $company->identification_number)
                                       ->where('customer', '=', $customer->company->identification_number)
                                       ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
                                       ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
                                       ->where('state_document_id', '=', 1)->get();
                        if(count($invoice) > 0){
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
            }
            else
              $at = '';
            return [
                'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
                'ResponseDian' => $respuestadian,
                'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NDS-{$resolution->next_consecutive}.xml"))),
                'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NDS-{$resolution->next_consecutive}.zip"))),
                'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ND-{$resolution->next_consecutive}.xml"))),
                'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqND-{$resolution->next_consecutive}.xml"))),
                'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaND-{$resolution->next_consecutive}.xml"))),
                'attacheddocument'=>base64_encode($at),
                'urlinvoicexml'=>"NDS-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"NDS-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"{$filename}.xml",
                'cude' => $signDebitNote->ConsultarCUDE(),
                'QRStr' => $QRStr
            ];
        }
    }

    /**
     * Test set store.
     *
     * @param \App\Http\Requests\Api\DebitNoteRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function testSetStore(DebitNoteRequest $request, $testSetId)
    {
        // User
        $user = auth()->user();

        // User company
        $company = $user->company;

        // Actualizar Tablas

        $this->ActualizarTablas();

        //Document
        $invoice_doc = new Document();
        $invoice_doc->request_api = json_encode($request->all());
        $invoice_doc->state_document_id = 0;
        $invoice_doc->type_document_id = $request->type_document_id;
        $invoice_doc->number = $request->number;
        $invoice_doc->client_id = 1;
        $invoice_doc->client =  $request->customer ;
        $invoice_doc->currency_id = 35;
        $invoice_doc->date_issue = date("Y-m-d H:i:s");
        $invoice_doc->sale = 1000;
        $invoice_doc->total_discount = 100;
        $invoice_doc->taxes =  $request->tax_totals;
        $invoice_doc->total_tax = 150;
        $invoice_doc->subtotal = 800;
        $invoice_doc->total = 1200;
        $invoice_doc->version_ubl_id = 1;
        $invoice_doc->ambient_id = 1;
        $invoice_doc->identification_number = $company->identification_number;
//        $invoice_doc->save();

        // Type document
        $typeDocument = TypeDocument::findOrFail($request->type_document_id);

        // Customer
        $customerAll = collect($request->customer);
        if(isset($customerAll['municipality_id_fact']))
            $customerAll['municipality_id'] = Municipality::where('codefacturador', $customerAll['municipality_id_fact'])->first();
        $customer = new User($customerAll->toArray());

        // Customer company
        $customer->company = new Company($customerAll->toArray());

        // Type operation id
        if(!$request->type_operation_id)
            $request->type_operation_id = 6;
        $typeoperation = TypeOperation::findOrFail($request->type_operation_id);

        // Currency id
        if(isset($request->idcurrency) and (!is_null($request->idcurrency))){
            $idcurrency = TypeCurrency::findOrFail($request->idcurrency);
            $calculationrate = $request->calculationrate;
            $calculationratedate = $request->calculationratedate;
        }
        else{
            $idcurrency = TypeCurrency::findOrFail($invoice_doc->currency_id);
            $calculationrate = 1;
            $calculationratedate = Carbon::now()->format('Y-m-d');
        }

        // Resolution
        $request->resolution->number = $request->number;
        $resolution = $request->resolution;

        // Date time
        $date = $request->date;
        $time = $request->time;

        // Notes
        $notes = $request->notes;

        // Order Reference
        if($request->order_reference)
            $orderreference = new OrderReference($request->order_reference);
        else
            $orderreference = NULL;

        // Health Fields
        if($request->health_fields)
            $healthfields = new HealthField($request->health_fields);
        else
            $healthfields = NULL;

        // Discrepancy response
        $discrepancycode = $request->discrepancyresponsecode;
        $discrepancydescription = $request->discrepancyresponsedescription;

        // Payment form default
        $paymentFormAll = (object) array_merge($this->paymentFormDefault, $request->payment_form ?? []);
        $paymentForm = PaymentForm::findOrFail($paymentFormAll->payment_form_id);
        $paymentForm->payment_method_code = PaymentMethod::findOrFail($paymentFormAll->payment_method_id)->code;
        $paymentForm->payment_due_date = $paymentFormAll->payment_due_date ?? null;
        $paymentForm->duration_measure = $paymentFormAll->duration_measure ?? null;

        // Allowance charges
        $allowanceCharges = collect();
        foreach ($request->allowance_charges ?? [] as $allowanceCharge) {
            $allowanceCharges->push(new AllowanceCharge($allowanceCharge));
        }

        // Tax totals
        $taxTotals = collect();
        foreach ($request->tax_totals ?? [] as $taxTotal) {
            $taxTotals->push(new TaxTotal($taxTotal));
        }

        // Retenciones globales
        $withHoldingTaxTotal = collect();
//        $withHoldingTaxTotalCount = 0;
//        $holdingTaxTotal = $request->holding_tax_total;
        foreach($request->with_holding_tax_total ?? [] as $item) {
//            $withHoldingTaxTotalCount++;
//            $holdingTaxTotal = $request->holding_tax_total;
            $withHoldingTaxTotal->push(new TaxTotal($item));
        }

        // Requested monetary totals
        $requestedMonetaryTotals = new LegalMonetaryTotal($request->requested_monetary_totals);

        // Debit note lines
        $debitNoteLines = collect();
        foreach ($request->debit_note_lines as $debitNoteLine) {
            $debitNoteLines->push(new DebitNoteLine($debitNoteLine));
        }

        // Billing reference
        $billingReference = new BillingReference($request->billing_reference);

        // Create XML
        $debitNote = $this->createXML(compact('user', 'company', 'customer', 'taxTotals', 'withHoldingTaxTotal', 'resolution', 'paymentForm', 'typeDocument', 'debitNoteLines', 'allowanceCharges', 'requestedMonetaryTotals', 'billingReference', 'date', 'time', 'notes', 'typeoperation', 'orderreference', 'discrepancycode', 'discrepancydescription', 'request', 'idcurrency', 'calculationrate', 'calculationratedate', 'healthfields'));

        // Register Customer
        if(env('APPLY_SEND_CUSTOMER_CREDENTIALS', TRUE))
            $this->registerCustomer($customer, $request->sendmail);
        else
            $this->registerCustomer($customer, $request->send_customer_credentials);

        // Signature XML
        $signDebitNote = new SignDebitNote($company->certificate->path, $company->certificate->password);
        $signDebitNote->softwareID = $company->software->identifier;
        $signDebitNote->pin = $company->software->pin;

        if ($request->GuardarEn){
            if (!is_dir($request->GuardarEn)) {
                mkdir($request->GuardarEn);
            }
        }
        else{
            if (!is_dir(storage_path("app/public/{$company->identification_number}"))) {
                mkdir(storage_path("app/public/{$company->identification_number}"));
            }
        }

        if ($request->GuardarEn)
            $signDebitNote->GuardarEn = $request->GuardarEn."\\ND-{$resolution->next_consecutive}.xml";
        else
            $signDebitNote->GuardarEn = storage_path("app/public/{$company->identification_number}/ND-{$resolution->next_consecutive}.xml");

        $sendTestSetAsync = new SendTestSetAsync($company->certificate->path, $company->certificate->password);
        $sendTestSetAsync->To = $company->software->url;
        $sendTestSetAsync->fileName = "{$resolution->next_consecutive}.xml";

        if ($request->GuardarEn)
            $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signDebitNote->sign($debitNote), $request->GuardarEn."\\NDS-{$resolution->next_consecutive}");
        else
            $sendTestSetAsync->contentFile = $this->zipBase64($company, $resolution, $signDebitNote->sign($debitNote), storage_path("app/public/{$company->identification_number}/NDS-{$resolution->next_consecutive}"));

        $sendTestSetAsync->testSetId = $testSetId;

        $QRStr = $this->createPDF($user, $company, $customer, $typeDocument, $resolution, $date, $time, $paymentForm, $request, $signDebitNote->ConsultarCUDE(), "ND", $withHoldingTaxTotal, $notes, $healthfields);

        $invoice_doc->prefix = $resolution->prefix;
        $invoice_doc->customer = $customer->company->identification_number;
        $invoice_doc->xml = "NDS-{$resolution->next_consecutive}.xml";
        $invoice_doc->pdf = "NDS-{$resolution->next_consecutive}.pdf";
        $invoice_doc->client_id = $customer->company->identification_number;
        $invoice_doc->client =  $request->customer ;
        if(property_exists($request, 'id_currency'))
            $invoice_doc->currency_id = $request->id_currency;
        else
            $invoice_doc->currency_id = 35;
        $invoice_doc->date_issue = date("Y-m-d H:i:s");
        $invoice_doc->sale = $requestedMonetaryTotals->payable_amount;
        $invoice_doc->total_discount = $requestedMonetaryTotals->allowance_total_amount ?? 0;
        $invoice_doc->taxes =  $request->tax_totals;
        $invoice_doc->total_tax = $requestedMonetaryTotals->tax_inclusive_amount - $requestedMonetaryTotals->tax_exclusive_amount;
        $invoice_doc->subtotal = $requestedMonetaryTotals->line_extension_amount;
        $invoice_doc->total = $requestedMonetaryTotals->payable_amount;
        $invoice_doc->version_ubl_id = 2;
        $invoice_doc->ambient_id = $company->type_environment_id;
        $invoice_doc->identification_number = $company->identification_number;
        $invoice_doc->save();

        if ($request->GuardarEn)
            return [
                'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
                'ResponseDian' => $sendTestSetAsync->signToSend($request->GuardarEn."\\ReqND-{$resolution->next_consecutive}.xml")->getResponseToObject($request->GuardarEn."\\RptaND-{$resolution->next_consecutive}.xml"),
                'invoicexml'=>base64_encode(file_get_contents($request->GuardarEn."\\NDS-{$resolution->next_consecutive}.xml")),
                'zipinvoicexml'=>base64_encode(file_get_contents($request->GuardarEn."\\NDS-{$resolution->next_consecutive}.zip")),
                'unsignedinvoicexml'=>base64_encode(file_get_contents($request->GuardarEn."\\ND-{$resolution->next_consecutive}.xml")),
                'reqfe'=>base64_encode(file_get_contents($request->GuardarEn."\\ReqND-{$resolution->next_consecutive}.xml")),
                'rptafe'=>base64_encode(file_get_contents($request->GuardarEn."\\RptaND-{$resolution->next_consecutive}.xml")),
                'urlinvoicexml'=>"NDS-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"NDS-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"Attachment-{$resolution->next_consecutive}.xml",
                'cude' => $signDebitNote->ConsultarCUDE(),
                'QRStr' => $QRStr
            ];
        else
            return [
                'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
                'ResponseDian' => $sendTestSetAsync->signToSend(storage_path("app/public/{$company->identification_number}/ReqND-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaND-{$resolution->next_consecutive}.xml")),
                'invoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NDS-{$resolution->next_consecutive}.xml"))),
                'zipinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NDS-{$resolution->next_consecutive}.zip"))),
                'unsignedinvoicexml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ND-{$resolution->next_consecutive}.xml"))),
                'reqfe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqND-{$resolution->next_consecutive}.xml"))),
                'rptafe'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaND-{$resolution->next_consecutive}.xml"))),
                'urlinvoicexml'=>"NDS-{$resolution->next_consecutive}.xml",
                'urlinvoicepdf'=>"NDS-{$resolution->next_consecutive}.pdf",
                'urlinvoiceattached'=>"Attachment-{$resolution->next_consecutive}.xml",
                'cude' => $signDebitNote->ConsultarCUDE(),
                'QRStr' => $QRStr
            ];
    }
}
