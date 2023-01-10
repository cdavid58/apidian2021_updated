<?php

namespace App\Http\Controllers\Api;

use App\User;
use App\Company;
use App\TypeDocument;
use App\Novelty;
use App\Period;
use App\Worker;
use App\TypeWorker;
use App\PayrollPayment;
use App\Accrued;
use App\Deduction;
use App\PayrollPaymentDate;
use App\DocumentPayroll;
use Illuminate\Http\Request;
use App\Traits\DocumentTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PayrollRequest;
use ubl21dian\XAdES\SignPayroll;
use ubl21dian\XAdES\SignAttachedDocument;
use ubl21dian\Templates\SOAP\SendPayrollASync;
use ubl21dian\Templates\SOAP\SendPayrollSync;
use ubl21dian\Templates\SOAP\SendTestSetAsync;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceMail;
use Storage;

class PayrollController extends Controller
{
    use DocumentTrait;

    /**
     * Store.
     *
     * @param \App\Http\Requests\Api\PayrollRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(PayrollRequest $request)
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

        // Type document
        $typeDocument = TypeDocument::findOrFail($request->type_document_id);

        // Novelty
        if($request->novelty)
            $novelty = new Novelty($request->novelty);
        else
            $novelty = NULL;

        // Period
        if($request->period)
            $period = new Period($request->period);
        else
            $period = NULL;

        // Worker
        if($request->worker)
            $worker = new Worker($request->worker);
        else
            $worker = NULL;

        // Payment
        if($request->payment)
            $payment = new PayrollPayment($request->payment);
        else
            $payment = NULL;

        // Payment Dates
        $payment_dates = collect();
        foreach ($request->payment_dates ?? [] as $payment_date) {
            $payment_dates->push(new PayrollPaymentDate($payment_date));
        }

        // Resolution
        $request->resolution->number = $request->consecutive;
        $resolution = $request->resolution;

        // Notes
        $notes = $request->notes;

        // Accrueds
        if($request->accrued)
            $accrued = new Accrued($request->accrued);
        else
            $accrued = NULL;

        // Deductions
        if($request->deductions)
            $deductions = new Deduction($request->deductions);
        else
            $deductions = NULL;

        // Splited Name
        $splited_name = $this->split_name($user->name);

        // Document Payroll
        $payroll_doc = new DocumentPayroll();
        $payroll_doc->identification_number = $company->identification_number;
        $payroll_doc->state_document_id = 0;
        $payroll_doc->type_document_id = $request->type_document_id;
        $payroll_doc->consecutive = $request->consecutive;
        $payroll_doc->employee_id = $worker->identification_number;
        $payroll_doc->date_issue = date("Y-m-d H:i:s");
        $payroll_doc->accrued_total = $accrued->accrued_total;
        $payroll_doc->deductions_total = $deductions->deductions_total;
        $payroll_doc->total_payroll =  $accrued->accrued_total - $deductions->deductions_total;
        $payroll_doc->request_api = json_encode($request->all());
        $payroll_doc->prefix = $resolution->prefix;
        $payroll_doc->xml = "NIS-{$resolution->next_consecutive}.xml";
        $payroll_doc->pdf = "NIS-{$resolution->next_consecutive}.pdf";
        $payroll_doc->save();

        // Create XML
        $payroll = $this->createXML(compact('user', 'company', 'novelty', 'period', 'worker', 'resolution', 'payment', 'payment_dates', 'typeDocument', 'notes', 'accrued', 'deductions', 'request', 'splited_name'));

        // Signature XML
        $signPayroll = new SignPayroll($company->certificate->path, $company->certificate->password);
        $signPayroll->softwareID = $company->software->identifier_payroll;
        $signPayroll->pin = $company->software->pin_payroll;

        if (!is_dir(storage_path("app/public/{$company->identification_number}")))
            mkdir(storage_path("app/public/{$company->identification_number}"));

        $signPayroll->GuardarEn = storage_path("app/public/{$company->identification_number}/NI-{$resolution->next_consecutive}.xml");

        $sendPayrollSync = new SendPayrollSync($company->certificate->path, $company->certificate->password);
        $sendPayrollSync->To = $company->software->url_payroll;
        $sendPayrollSync->fileName = "{$resolution->next_consecutive}.xml";
        $sendPayrollSync->contentFile = $this->zipBase64($company, $resolution, $signPayroll->sign($payroll), storage_path("app/public/{$company->identification_number}/NIS-{$resolution->next_consecutive}"));

        $QRStr = $this->createPDFPayroll($user, $company, $novelty, $period, $worker, $resolution, $payment, $payment_dates, $typeDocument, $notes, $accrued, $deductions, $request, $signPayroll->ConsultarCUNE(), "NA");

        $filename = '';
        $respuestadian = '';
        $typeDocument = TypeDocument::findOrFail(7);
//        $xml = new \DOMDocument;
        $ar = new \DOMDocument;
        $respuestadian = $sendPayrollSync->signToSend(storage_path("app/public/{$company->identification_number}/ReqNI-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaNI-{$resolution->next_consecutive}.xml"));
//        return $QRStr;
//        return $payroll->saveXML();
//        return json_encode($respuestadian);
        if($respuestadian->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult->IsValid == 'true'){
            $filename = str_replace('ni', 'ad', str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $respuestadian->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult->XmlFileName))));
            if($request->atacheddocument_name_prefix)
                $filename = $request->atacheddocument_name_prefix.$filename;
            $cufecude = $respuestadian->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult->XmlDocumentKey;
            $payroll_doc->state_document_id = 1;
            $payroll_doc->cune = $cufecude;
            $payroll_doc->save();
            $signedxml = file_get_contents(storage_path("app/xml/{$company->id}/".$respuestadian->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult->XmlFileName.".xml"));
//            $xml->loadXML($signedxml);
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

            $appresponsexml = base64_decode($respuestadian->Envelope->Body->SendNominaSyncResponse->SendNominaSyncResult->XmlBase64Bytes);
            $ar->loadXML($appresponsexml);
            $fechavalidacion = $ar->documentElement->getElementsByTagName('IssueDate')->item(0)->nodeValue;
            $horavalidacion = $ar->documentElement->getElementsByTagName('IssueTime')->item(0)->nodeValue;
            $at = '';
            // Create XML AttachedDocument
//            $attacheddocument = $this->createXML(compact('user', 'company', 'worker', 'resolution', 'typeDocument', 'cufecude', 'signedxml', 'appresponsexml', 'fechavalidacion', 'horavalidacion'));
//                // Signature XML
//                $signAttachedDocument = new SignAttachedDocument($company->certificate->path, $company->certificate->password);
//                $signAttachedDocument->GuardarEn = storage_path("app/public/{$company->identification_number}/{$filename}.xml");
//
//                $at = $signAttachedDocument->sign($attacheddocument)->xml;
//            $at = str_replace("&gt;", ">", str_replace("&quot;", '"', str_replace("&lt;", "<", $at)));
//            $file = fopen(storage_path("app/public/{$company->identification_number}/{$filename}".".xml"), "w");
////            $file = fopen(storage_path("app/public/{$company->identification_number}/Attachment-".$this->valueXML($signedxml, $td."/cbc:ID/").".xml"), "w");
//            fwrite($file, $at);
//            fclose($file);
//            if(isset($request->sendmail))
//                if($request->sendmail){
//                    $payroll = Document::where('identification_number', '=', $company->identification_number)
//                                       ->where('employee_id', '=', $worker->identification_number)
//                                       ->where('prefix', '=', $this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"))
//                                       ->where('number', '=', str_replace($this->ValueXML($signedxml, $td."/cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cac:CorporateRegistrationScheme/cbc:ID/"), '', $this->ValueXML($signedxml, $td."/cbc:ID/")))
//                                       ->where('state_document_id', '=', 1)->get();
//                    if(count($payroll) > 0){
//                        Mail::to($worker->email)->send(new PayrollMail($invoice, $customer, $company, FALSE, FALSE, $filename, TRUE, $request));
//                        if($request->sendmailtome)
//                            Mail::to($user->email)->send(new PayrollMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
//                            if($request->email_cc_list){
//                                foreach($request->email_cc_list as $email)
//                                  Mail::to($email)->send(new PayrollMail($invoice, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
//                            }
//                    }
//                }
        }
        else
            $at = '';
        return [
            'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
            'ResponseDian' => $respuestadian,
            'payrollxml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NIS-{$resolution->next_consecutive}.xml"))),
            'zippayrollxml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NIS-{$resolution->next_consecutive}.zip"))),
            'unsignedpayrollxml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NI-{$resolution->next_consecutive}.xml"))),
            'reqni'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqNI-{$resolution->next_consecutive}.xml"))),
            'rptani'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaNI-{$resolution->next_consecutive}.xml"))),
            'attacheddocument'=>base64_encode($at),
            'urlpayrollxml'=>"NIS-{$resolution->next_consecutive}.xml",
            'urlpayrollpdf'=>"NIS-{$resolution->next_consecutive}.pdf",
            'urlpayrollattached'=>"{$filename}.xml",
            'cune' => $signPayroll->ConsultarCUNE(),
            'QRStr' => $QRStr
        ];
    }

    /**
     * Test set store.
     *
     * @param \App\Http\Requests\Api\PayrollRequest $request
     * @param string                                $testSetId
     *
     * @return \Illuminate\Http\Response
     */
    public function testSetStore(PayrollRequest $request, $testSetId)
    {
        // User
        $user = auth()->user();

        // User company
        $company = $user->company;

        // Type document
        $typeDocument = TypeDocument::findOrFail($request->type_document_id);

        // Novelty
        if($request->novelty)
            $novelty = new Novelty($request->novelty);
        else
            $novelty = NULL;

        // Period
        if($request->period)
            $period = new Period($request->period);
        else
            $period = NULL;

        // Worker
        if($request->worker)
            $worker = new Worker($request->worker);
        else
            $worker = NULL;

        // Payment
        if($request->payment)
            $payment = new PayrollPayment($request->payment);
        else
            $payment = NULL;

        // Payment Dates
        $payment_dates = collect();
        foreach ($request->payment_dates ?? [] as $payment_date) {
            $payment_dates->push(new PayrollPaymentDate($payment_date));
        }

        // Resolution
        $request->resolution->number = $request->consecutive;
        $resolution = $request->resolution;

        // Notes
        $notes = $request->notes;

        // Accrueds
        if($request->accrued)
            $accrued = new Accrued($request->accrued);
        else
            $accrued = NULL;

        // Deductions
        if($request->deductions)
            $deductions = new Deduction($request->deductions);
        else
            $deductions = NULL;

        // Splited Name
        $splited_name = $this->split_name($user->name);

        // Document Payroll
        $payroll_doc = new DocumentPayroll();
        $payroll_doc->identification_number = $company->identification_number;
        $payroll_doc->state_document_id = 0;
        $payroll_doc->type_document_id = $request->type_document_id;
        $payroll_doc->consecutive = $request->consecutive;
        $payroll_doc->employee_id = $worker->identification_number;
        $payroll_doc->date_issue = date("Y-m-d H:i:s");
        $payroll_doc->accrued_total = $accrued->accrued_total;
        $payroll_doc->deductions_total = $deductions->deductions_total;
        $payroll_doc->total_payroll =  $accrued->accrued_total - $deductions->deductions_total;
        $payroll_doc->request_api = json_encode($request->all());
        $payroll_doc->prefix = $resolution->prefix;
        $payroll_doc->xml = "NIS-{$resolution->next_consecutive}.xml";
        $payroll_doc->pdf = "NIS-{$resolution->next_consecutive}.pdf";
        $payroll_doc->save();

        // Create XML
        $payroll = $this->createXML(compact('user', 'company', 'novelty', 'period', 'worker', 'resolution', 'payment', 'payment_dates', 'typeDocument', 'notes', 'accrued', 'deductions', 'request', 'splited_name'));

        // Signature XML
        $signPayroll = new SignPayroll($company->certificate->path, $company->certificate->password);
        $signPayroll->softwareID = $company->software->identifier_payroll;
        $signPayroll->pin = $company->software->pin_payroll;

        if (!is_dir(storage_path("app/public/{$company->identification_number}")))
            mkdir(storage_path("app/public/{$company->identification_number}"));

        $signPayroll->GuardarEn = storage_path("app/public/{$company->identification_number}/NI-{$resolution->next_consecutive}.xml");

        $sendPayrollASync = new SendPayrollASync($company->certificate->path, $company->certificate->password);
        $sendPayrollASync->To = $company->software->url_payroll;
        $sendPayrollASync->fileName = "{$resolution->next_consecutive}.xml";
        $sendPayrollASync->contentFile = $this->zipBase64($company, $resolution, $signPayroll->sign($payroll), storage_path("app/public/{$company->identification_number}/NIS-{$resolution->next_consecutive}"));
        $sendPayrollASync->testSetId = $testSetId;

        $QRStr = $this->createPDFPayroll($user, $company, $novelty, $period, $worker, $resolution, $payment, $payment_dates, $typeDocument, $notes, $accrued, $deductions, $request, $signPayroll->ConsultarCUNE(), "PAYROLL");

        return [
            'message' => "{$typeDocument->name} #{$resolution->next_consecutive} generada con éxito",
            'ResponseDian' => $sendPayrollASync->signToSend(storage_path("app/public/{$company->identification_number}/ReqNI-{$resolution->next_consecutive}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaNI-{$resolution->next_consecutive}.xml")),
            'payrollxml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NIS-{$resolution->next_consecutive}.xml"))),
            'zippayrollxml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NIS-{$resolution->next_consecutive}.zip"))),
            'unsignedpayrollxml'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/NI-{$resolution->next_consecutive}.xml"))),
            'reqni'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/ReqNI-{$resolution->next_consecutive}.xml"))),
            'rptani'=>base64_encode(file_get_contents(storage_path("app/public/{$company->identification_number}/RptaNI-{$resolution->next_consecutive}.xml"))),
            'urlpayrollxml'=>"NIS-{$resolution->next_consecutive}.xml",
            'urlpayrollpdf'=>"NIS-{$resolution->next_consecutive}.pdf",
            'cune' => $signPayroll->ConsultarCUNE(),
            'QRStr' => $QRStr
        ];
    }


    public function currentNumber($type, $prefix = null)
    {
        // User
        $user = auth()->user();

        // User company
        $company = $user->company;
        $resolution = $company->resolutions->where('type_document_id', $type)->first();

        if($prefix)
        {
            $resolution = $company->resolutions->where('type_document_id', $type)->where('prefix', $prefix)->first();
        }

        $document_payroll = DocumentPayroll::where('type_document_id', $type)
                                            ->where('state_document_id', 1)
                                            ->where('prefix', $resolution->prefix)
                                            ->get()->sortByDesc('consecutive')->first();

        return [
            'number' => ($document_payroll) ? ((int)$document_payroll->consecutive + 1) : (int)$resolution->from,
            'success' => true,
            'prefix' => $resolution->prefix
        ];

    }

}
