<?php

namespace App\Http\Controllers\Api;

use App\User;
use App\Company;
use App\TypeDocument;
use App\TypeRejection;
use App\DocumentReference;
use App\IssuerParty;
use App\Event;
use Illuminate\Http\Request;
use App\Traits\DocumentTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SendEventRequest;
use ubl21dian\XAdES\SignEvent;
use ubl21dian\Templates\SOAP\SendEvent;
use Storage;

class SendEventController extends Controller
{
    use DocumentTrait;

    /**
     * Store.
     *
     * @param \App\Http\Requests\Api\SendEventRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function sendevent(SendEventRequest $request, $company_idnumber = FALSE)
    {
        // User company
        if($company_idnumber){
            $company = Company::where('identification_number', $company_idnumber)->firstOrFail();
            $user = User::where('id', $company->user_id)->firstOrFail();
        }
        else{
            $user = auth()->user();
            $company = $user->company;
        }

        // Actualizar Tablas

        $this->ActualizarTablas();

        // Type document
        $typeDocument = TypeDocument::findOrFail(8);

        // Type document - document reference
        $typeDocumentReference = TypeDocument::findOrFail($request->document_reference['type_document_id']);

        // Event code
        $event = Event::findOrFail($request->event_id);

        // Sender
        $senderAll = collect($request->sender);
        $sender = new User($senderAll->toArray());

        // Customer company
        $sender->company = new Company($senderAll->toArray());

        // Document reference
        $documentReference = new DocumentReference($request->document_reference);

        // Issuer Party
        if($request->issuer_party)
            $issuerparty = new IssuerParty($request->issuer_party);
        else
            $issuerparty = NULL;

        // Rejection Id
        if($request->type_rejection_id)
            $typerejection = TypeRejection::where('id', $request->type_rejection_id)->firstOrFail();
        else
            $typerejection = NULL;

        // Create XML
        $eventXML = $this->createXML(compact('user', 'company', 'typeDocument', 'event', 'sender', 'documentReference', 'typeDocumentReference', 'issuerparty', 'typerejection'));

        // Signature XML
        $signEvent = new SignEvent($company->certificate->path, $company->certificate->password);
        $signEvent->softwareID = $company->software->identifier;
        $signEvent->pin = $company->software->pin;

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
            $signEvent->GuardarEn = $request->GuardarEn."\\EV-{$event->code}-{$sender->company->identification_number}-{$documentReference->getPrefixAttribute()}-{$documentReference->getNumberAttribute()}.xml";
        else
            $signEvent->GuardarEn = storage_path("app/public/{$company->identification_number}/EV-{$event->code}-{$sender->company->identification_number}-{$documentReference->getPrefixAttribute()}-{$documentReference->getNumberAttribute()}.xml");

        $sendEvent = new SendEvent($company->certificate->path, $company->certificate->password);
        $sendEvent->To = $company->software->url;
        if ($request->GuardarEn)
            $sendEvent->contentFile = $this->zipBase64SendEvent($company, $event->code, $sender->company->identification_number, $documentReference->getPrefixAttribute().$documentReference->getNumberAttribute(), $signEvent->sign($eventXML), $request->GuardarEn."\\EVS-{$event->code}-{$sender->company->identification_number}-{$documentReference->getPrefixAttribute()}-{$documentReference->getNumberAttribute()}");
        else
            $sendEvent->contentFile = $this->zipBase64SendEvent($company, $event->code, $sender->company->identification_number, $documentReference->getPrefixAttribute().$documentReference->getNumberAttribute(), $signEvent->sign($eventXML), storage_path("app/public/{$company->identification_number}/EVS-{$event->code}-{$sender->company->identification_number}-{$documentReference->getPrefixAttribute()}-{$documentReference->getNumberAttribute()}"));

        if ($request->GuardarEn){
            return [
                'message' => "{$typeDocument->name} #{$documentReference->getPrefixAttribute()}{$documentReference->getNumberAttribute()} generada con éxito",
                'ResponseDian' => $respuestadian,
                'cufe' => $signEvent->ConsultarCUDEEVENT()
            ];
        }
        else{
            $respuestadian = $sendEvent->signToSend(storage_path("app/public/{$company->identification_number}/ReqEV-{$event->code}-{$sender->company->identification_number}-{$documentReference->getPrefixAttribute()}-{$documentReference->getNumberAttribute()}.xml"))->getResponseToObject(storage_path("app/public/{$company->identification_number}/RptaEV-{$event->code}-{$sender->company->identification_number}-{$documentReference->getPrefixAttribute()}-{$documentReference->getNumberAttribute()}.xml"));
            return [
                'message' => "{$typeDocument->name} #{$documentReference->getPrefixAttribute()}{$documentReference->getNumberAttribute()} generada con éxito",
                'ResponseDian' => $respuestadian,
                'cude' => $signEvent->ConsultarCUDEEVENT()
            ];
        }
    }
}
