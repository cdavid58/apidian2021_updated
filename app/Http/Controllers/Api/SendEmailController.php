<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\DocumentTrait;
use App\Mail\InvoiceMail;
use App\Mail\PasswordCustomerMail;
use App\User;
use App\Customer;
use App\Document;
use App\Company;
use App\Http\Requests\Api\SendEmailRequest;
use Illuminate\Support\Facades\Mail;
use Exception;
use App\Http\Requests\Api\SendPayrollEmailRequest;
use App\DocumentPayroll;
use App\Mail\DocumentPayrollEmail;


class SendEmailController extends Controller
{
    use DocumentTrait;

    /**
     * SendEmail.
     *
     *
     * @return array
     */

    public function SendEmail(SendEmailRequest $request, $GuardarEn = FALSE)
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

        $document = Document::where('identification_number', '=', $company->identification_number)
                            ->where('prefix', '=', $request->prefix)
                            ->where('number', '=', $request->number)
                            ->where('state_document_id', '=', 1)->get();
        if(sizeof($document) == 0)
            return [
                'message' => "Documento {$request->prefix}-{$request->number} no existe en la base de datos.",
                'success' => FALSE,
            ];

        $customer = Customer::findOrFail($document[0]->customer);
        if($request->alternate_email)
            $email = $request->alternate_email;
        else
            $email = $customer->email;

        if($document[0]->type_document_id == 1)
            $rptafe = file_get_contents(storage_path("app/public/{$company->identification_number}/"."RptaFE-".$document[0]->prefix.$document[0]->number.".xml"));
        else
            if($document[0]->type_document_id == 4)
                $rptafe = file_get_contents(storage_path("app/public/{$company->identification_number}/"."RptaNC-".$document[0]->prefix.$document[0]->number.".xml"));
            else
                $rptafe = file_get_contents(storage_path("app/public/{$company->identification_number}/"."RptaND-".$document[0]->prefix.$document[0]->number.".xml"));
        $filename = str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $this->getTag($rptafe, 'XmlFileName')->nodeValue)));
        if ($GuardarEn){
            if($request->base64graphicrepresentation)
                Mail::to($email)->send(new InvoiceMail($document, $customer, $company, $GuardarEn, $request->base64graphicrepresentation, $filename, FALSE, $request));
            else
                Mail::to($email)->send(new InvoiceMail($document, $customer, $company, $GuardarEn, FALSE, $filename, FALSE, $request));
        }
        else{
            if($request->base64graphicrepresentation)
                Mail::to($email)->send(new InvoiceMail($document, $customer, $company, FALSE, $request->base64graphicrepresentation, $filename, FALSE, $request));
            else
                Mail::to($email)->send(new InvoiceMail($document, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
        }
        return [
            'message' => 'Envio realizado con éxito',
            'success' => TRUE,
        ];
    }

    /**
     * SendEmail Customer.
     *
     *
     * @return view
     */

    public function SendEmailCustomer(SendEmailRequest $request, $ShowView = 'YES')
    {
        $company = Company::where('identification_number', '=', $request->company_idnumber)->first();
        // User
        $user = User::where('id', $company->user_id)->firstOrFail();

        if($user->validate_mail_server()){
            \Config::set('mail.host', $user->mail_host);
            \Config::set('mail.port', $user->mail_port);
            \Config::set('mail.username', $user->mail_username);
            \Config::set('mail.password', $user->mail_password);
            \Config::set('mail.encryption', $user->mail_encryption);
        }

        if(empty($company))
            return view('customerloginmensaje', ['titulo' => 'Error al realizar el envio.',
                                                'mensaje' => 'Esta compañia no existe en la base de datos.']);

        $document = Document::where('identification_number', '=', $company->identification_number)
                            ->where('prefix', '=', $request->prefix)
                            ->where('number', '=', $request->number)
                            ->where('state_document_id', '=', 1)->get();
        if(sizeof($document) == 0)
            return view('customerloginmensaje', ['titulo' => 'Error al realizar el envio.',
                                                'mensaje' => 'Este documento no existe en la base de datos.']);

        $customer = Customer::findOrFail($document[0]->customer);
        if($document[0]->type_document_id == 1)
            $rptafe = file_get_contents(storage_path("app/public/{$company->identification_number}/"."RptaFE-".$document[0]->prefix.$document[0]->number.".xml"));
        else
            if($document[0]->type_document_id == 4)
                $rptafe = file_get_contents(storage_path("app/public/{$company->identification_number}/"."RptaNC-".$document[0]->prefix.$document[0]->number.".xml"));
            else
                $rptafe = file_get_contents(storage_path("app/public/{$company->identification_number}/"."RptaND-".$document[0]->prefix.$document[0]->number.".xml"));
        $filename = str_replace('nd', 'ad', str_replace('nc', 'ad', str_replace('fv', 'ad', $this->getTag($rptafe, 'XmlFileName')->nodeValue)));
//        return $user->email;
        if($filename <> ''){
            Mail::to($customer->email)->send(new InvoiceMail($document, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
            Mail::to($user->email)->send(new InvoiceMail($document, $customer, $company, FALSE, FALSE, $filename, FALSE, $request));
        }
        if($ShowView == 'YES')
//            return redirect('/homecustomers');
            if($filename <> '')
                return view('customerloginmensaje', ['titulo' => 'Envio realizado con exito.',
                                                     'mensaje' => 'El Documento se envio satisfactoriamente.']);
            else
                return view('customerloginmensaje', ['titulo' => 'Error al realizar el envio.',
                                                     'mensaje' => 'El Documento no se pudo enviar.']);
        else
            if($filename <> '')
                return [
                    'message' => 'Envio realizado con éxito',
                    'success' => TRUE,
                ];
            else
                return [
                    'message' => 'Error al realizar el envio',
                    'success' => FALSE,
                ];

    }

    /**
     * SendEmail Password Customer.
     *
     *
     * @return view
     */

    public function SendEmailPasswordCustomer(Request $request)
    {
        $customer = Customer::findOrFail($request->identification_number);
        Mail::to($customer->email)->send(new PasswordCustomerMail($customer));
        return [
            'message' => 'Correo electronico para credenciales, enviado con exito.',
            'success' => 'true',
        ];
//        return view('customerloginmensaje', ['titulo' => 'Envio realizado con exito.',
//                                            'mensaje' => 'El Documento se envio satisfactoriamente.']);
    }

   
    /**
     * Envio de correo nomina
     *
     * @param  SendPayrollEmailRequest $request
     * @return void
     */
    public function sendEmailDocumentPayroll(SendPayrollEmailRequest $request)
    {
        
        try {

            $user = auth()->user();

            if($user->validate_mail_server()){
                \Config::set('mail.host', $user->mail_host);
                \Config::set('mail.port', $user->mail_port);
                \Config::set('mail.username', $user->mail_username);
                \Config::set('mail.password', $user->mail_password);
                \Config::set('mail.encryption', $user->mail_encryption);
            }

            $company = $user->company;
            $email = $request->email;
            $document = DocumentPayroll::whereExistRecord($company->identification_number, $request->prefix, $request->consecutive)->first();

            if(!$document)
            {
                return [
                    'success' => false,
                    'message' => "Nómina {$request->prefix}-{$request->consecutive} no existe en la base de datos.",
                ];
            }

            // dd($document->type_document->name);
            Mail::to($email)->send(new DocumentPayrollEmail($user, $document));

            return [
                'success' => true,
                'message' => 'El correo fue enviado satisfactoriamente',
            ];

        } catch (Exception $e) {

            return [
                'success' => false,
                'message' => "Ocurrió un error inesperado: {$e->getCode()} - {$e->getMessage()} - {$e->getFile()}  - {$e->getLine()}"
            ];
            
        }

    }

}
