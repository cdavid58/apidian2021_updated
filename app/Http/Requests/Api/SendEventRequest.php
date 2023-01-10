<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'event_id' => 'required|exists:events,id',

            // Sender
            'sender' => 'required|array',
            'sender.identification_number' => 'required|numeric|digits_between:1,15',
            'sender.dv' => 'nullable|numeric|digits:1|dian_dv:'.$this->sender["identification_number"],
            'sender.type_document_identification_id' => 'nullable|exists:type_document_identifications,id',
            'sender.type_organization_id' => 'nullable|exists:type_organizations,id',
            'sender.language_id' => 'nullable|exists:languages,id',
            'sender.country_id' => 'nullable|exists:countries,id',
            'sender.municipality_id' => 'nullable|exists:municipalities,id',
            'sender.type_regime_id' => 'nullable|exists:type_regimes,id',
            'sender.tax_id' => 'required|exists:taxes,id',
            'sender.type_liability_id' => 'nullable|exists:type_liabilities,id',
            'sender.name' => 'required|string',
            'sender.phone' => 'nullable|numeric|digits_between:7,10',
            'sender.address' => 'nullable|string',
            'sender.email' => 'nullable|string|email',
            'sender.merchant_registration' => 'nullable|string',

            // Document Reference
            'document_reference' => 'required|array',
            'document_reference.prefix' => 'nullable|string',
            'document_reference.number' => 'required|string',
            'document_reference.uuid' => 'required|string|size:96',
            'document_reference.type_document_id' => 'required|exists:type_documents,id',

            // Issuer Party
            'issuer_party' => 'nullable|array',
            'issuer_party.identification_number' => 'nullable|required_with:issuer_party|string',
            'issuer_party.first_name' => 'nullable|required_with:issuer_party|string',
            'issuer_party.first_name' => 'nullable|required_with:issuer_party|string',
            'issuer_party.organization_department' => 'nullable|required_with:issuer_party|string',
            'issuer_party.job_title' => 'nullable|required_with:issuer_party|string',

            // Type Rejection
            'type_rejection_id' => 'nullable|required_if:event_id,2|exists:type_rejections,id'
        ];
    }
}
