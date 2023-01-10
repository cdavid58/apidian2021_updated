<?php

namespace App\Http\Requests\Api;

use App\Rules\ResolutionSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DebitNoteRequest extends FormRequest
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
        $this->count_resolutions = auth()->user()->company->resolutions->where('type_document_id', $this->type_document_id)->count();
        if($this->count_resolutions < 2)
            $this->resolution = auth()->user()->company->resolutions->where('type_document_id', $this->type_document_id)->first();
        else{
            $this->count_resolutions = auth()->user()->company->resolutions->where('type_document_id', $this->type_document_id)->where('resolution', $this->resolution_number)->count();
            if($this->count_resolutions < 2)
                $this->resolution = auth()->user()->company->resolutions->where('type_document_id', $this->type_document_id)->where('resolution', $this->resolution_number)->first();
            else
                $this->resolution = auth()->user()->company->resolutions->where('type_document_id', $this->type_document_id)->where('resolution', $this->resolution_number)->where('prefix', $this->prefix)->first();
        }

        return [
            // Adicionales Facturador
            'ivaresponsable' => 'nullable|string',
            'nombretipodocid' => 'nullable|string',
            'tarifaica' => 'nullable|string',
            'actividadeconomica' => 'nullable|string',

            // Datos del Establecimiento
            'establishment_name' => 'nullable|string',
            'establishment_address' => 'nullable|string',
            'establishment_phone' => 'nullable|numeric|digits_between:7,10',
            'establishment_municipality' => 'nullable|exists:municipalities,id',
            'establishment_email' => 'nullable|string|email',
            'establishment_logo' => 'nullable|string',

            // Prefijo del Nombre del AttachedDocument
            'atacheddocument_name_prefix' => 'nullable|string',

            // Regimen SEZE
            'seze' => 'nullable|string',  // Cadena indicando año de inicio regimen SEZE y año de formacion de sociedad separados por guion Ejemplo 2021-2017

            // Nota Encabezado y pie de pagina
            'foot_note' => 'nullable|string',
            'head_note' => 'nullable|string',

            // Desactivar texto de confirmacion de pago
            'disable_confirmation_text' => 'nullable|boolean',

            // Enviar Correo al Adquiriente
            'sendmail' => 'nullable|boolean',
            'sendmailtome' => 'nullable|boolean',
            'send_customer_credentials' => 'nullable|boolean',

            // Lista de correos a enviar copia
            'email_cc_list' => 'nullable|array',
            'email_cc_list.*.email' => 'nullable|required_with:email_cc_list,|string|email',

            // HTML string body email
            'html_header' => 'nullable|string',
            'html_body' => 'nullable|string',
            'html_buttons' => 'nullable|string',
            'html_footer' => 'nullable|string',

            // Nombre Archivo
            'GuardarEn' => 'nullable|string',

            // Document
            'type_document_id' => [
                'required',
                'in:5',
                'exists:type_documents,id',
                new ResolutionSetting(),
            ],

            // Resolution number for document sending
            'resolution_number' => Rule::requiredIf(function(){
                if(auth()->user()->company->resolutions->where('type_document_id', $this->type_document_id)->count() >= 2)
                  return true;
                else
                  return false;
            }),

            // Prefijo de la resolucion a utilizar

            'prefix' => Rule::requiredIf(function(){
                if(auth()->user()->company->resolutions->where('type_document_id', $this->type_document_id)->where('resolution_number', $this->resolution_number)->count() >= 2)
                    return true;
                else
                    return false;
            }),

            // Consecutive
            'number' => 'required|integer|between:'.optional($this->resolution)->from.','.optional($this->resolution)->to,

            // Discrepancy Response
            'discrepancyresponsecode' => 'nullable|integer|between:1,4',
            'discrepancyresponsedescription' => 'nullable|string',

            // Date time
            'date' => 'nullable|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i:s',

            // Notes
            'notes' => 'nullable|string',

            // Tipo operacion
            'type_operation_id' => 'nullable|numeric|exists:type_operations',

            // Billing Reference
            'billing_reference' => 'required|array',
            'billing_reference.number' => 'required|string',
            'billing_reference.uuid' => 'required|string|size:96',
            'billing_reference.issue_date' => 'required|date_format:Y-m-d',

            // Id moneda negociacion
            'idcurrency' => 'nullable|integer|exists:type_currencies,id',
            'calculationrate' => 'nullable|required_with:idcurrency|numeric',
            'calculationratedate' => 'nullable|required_with:idcurrency|date_format:Y-m-d',

            // Customer
            'customer' => 'required|array',
            'customer.identification_number' => 'required|alpha_num|between:1,15',
            'customer.dv' => 'nullable|numeric|digits:1|dian_dv:'.$this->customer["identification_number"],
            'customer.type_document_identification_id' => 'nullable|exists:type_document_identifications,id',
            'customer.type_organization_id' => 'nullable|exists:type_organizations,id',
            'customer.language_id' => 'nullable|exists:languages,id',
            'customer.country_id' => 'nullable|exists:countries,id',
            'customer.municipality_id' => 'nullable|exists:municipalities,id',
            'customer.municipality_id_fact' => 'nullable|exists:municipalities,codefacturador',
            'customer.type_regime_id' => 'nullable|exists:type_regimes,id',
            'customer.tax_id' => 'nullable|exists:taxes,id',
            'customer.type_liability_id' => 'nullable|exists:type_liabilities,id',
            'customer.name' => 'required|string',
            'customer.phone' => 'required_unless:customer.identification_number,222222222222|numeric|digits_between:7,10',
            'customer.address' => 'required_unless:customer.identification_number,222222222222|string',
            'customer.email' => 'required_unless:customer.identification_number,222222222222|string|email',
            'customer.merchant_registration' => 'required|string',

            // Order Reference
            'order_reference' => 'nullable|array',
            'order_reference.id_order' => 'nullable|string',
            'order_reference.issue_date_order' => 'nullable|date_format:Y-m-d',

            // Health Fields
            'health_fields' => 'nullable|array',
            'health_fields.invoice_period_start_date' => 'nullable|required_with:health_fields|date_format:Y-m-d',
            'health_fields.invoice_period_end_date' => 'nullable|required_with:health_fields|date_format:Y-m-d',
            'health_fields.*.users_info' => 'nullable|array',
            'health_fields.*.users_info.*.provider_code' => 'nullable|string',
            'health_fields.*.users_info.*.health_type_document_identification_id' => 'nullable|exists:health_type_document_identifications,id',
            'health_fields.*.users_info.*.identification_number' => 'nullable|alpha_num|between:3,16',
            'health_fields.*.users_info.*.surname' => 'nullable|string',
            'health_fields.*.users_info.*.second_surname' => 'nullable|string',
            'health_fields.*.users_info.*.first_name' => 'nullable|string',
            'health_fields.*.users_info.*.middle_name' => 'nullable|string',
            'health_fields.*.users_info.*.health_type_user_id' => 'nullable|exists:health_type_users,id',
            'health_fields.*.users_info.*.health_contracting_payment_method_id' => 'nullable|required_with:health_fields|exists:health_contracting_payment_methods,id',
            'health_fields.*.users_info.*.health_coverage_id' => 'nullable|required_with:health_fields|exists:health_coverages,id',
            'health_fields.*.users_info.*.autorization_numbers' => 'nullable|string',
            'health_fields.*.users_info.*.mipres' => 'nullable|string',
            'health_fields.*.users_info.*.mipres_delivery' => 'nullable|string',
            'health_fields.*.users_info.*.contract_number' => 'nullable|string',
            'health_fields.*.users_info.*.policy_number' => 'nullable|string',
            'health_fields.*.users_info.*.co_payment' => 'nullable|numeric|min:0|not_in:0',
            'health_fields.*.users_info.*.moderating_fee' => 'nullable|numeric|min:0|not_in:0',
            'health_fields.*.users_info.*.recovery_fee' => 'nullable|numeric|min:0|not_in:0',
            'health_fields.*.users_info.*.shared_payment' => 'nullable|numeric|min:0|not_in:0',

            // Payment form
            'payment_form' => 'nullable|array',
            'payment_form.payment_form_id' => 'nullable|exists:payment_forms,id',
            'payment_form.payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_form.payment_due_date' => 'nullable|required_if:payment_form.payment_form_id,=,2|after_or_equal:date|date_format:Y-m-d',
            'payment_form.duration_measure' => 'nullable|required_if:payment_form.payment_form_id,=,2|numeric|digits_between:1,3',

            // Allowance charges
            'allowance_charges' => 'nullable|array',
            'allowance_charges.*.charge_indicator' => 'nullable|required_with:allowance_charges|boolean',
            'allowance_charges.*.discount_id' => 'nullable|required_if:allowance_charges.*.charge_indicator,false|exists:discounts,id',
            'allowance_charges.*.allowance_charge_reason' => 'nullable|required_with:allowance_charges|string',
            'allowance_charges.*.amount' => 'nullable|required_with:allowance_charges|numeric',
            'allowance_charges.*.base_amount' => 'nullable|required_with:allowance_charges|numeric',

            // Holding Tax totals
            'with_holding_tax_total' => 'nullable|array',
            'with_holding_tax_total.*.tax_id' => 'nullable|exists:taxes,id|numeric',
            'with_holding_tax_total.*.percent' => 'nullable|numeric',
            'with_holding_tax_total.*.tax_amount' => 'nullable|numeric',
            'with_holding_tax_total.*.taxable_amount' => 'nullable|numeric',
            'with_holding_tax_total.*.unit_measure_id' => 'nullable|exists:unit_measures,id',
            'with_holding_tax_total.*.per_unit_amount' => 'nullable|numeric',
            'with_holding_tax_total.*.base_unit_measure' => 'nullable|numeric',

            // Tax totals
            'tax_totals' => 'nullable|array',
            'tax_totals.*.tax_id' => 'nullable|required_with:allowance_charges|exists:taxes,id',
            'tax_totals.*.percent' => 'nullable|required_unless:tax_totals.*.tax_id,10|numeric',
            'tax_totals.*.tax_amount' => 'nullable|required_with:allowance_charges|numeric',
            'tax_totals.*.taxable_amount' => 'nullable|required_with:allowance_charges|numeric',
            'tax_totals.*.unit_measure_id' => 'nullable|required_if:tax_totals.*.tax_id,10|exists:unit_measures,id',
            'tax_totals.*.per_unit_amount' => 'nullable|required_if:tax_totals.*.tax_id,10|numeric',
            'tax_totals.*.base_unit_measure' => 'nullable|required_if:tax_totals.*.tax_id,10|numeric',

            // Requested monetary totals
            'requested_monetary_totals' => 'required|array',
            'requested_monetary_totals.line_extension_amount' => 'required|numeric',
            'requested_monetary_totals.tax_exclusive_amount' => 'required|numeric',
            'requested_monetary_totals.tax_inclusive_amount' => 'required|numeric',
            'requested_monetary_totals.allowance_total_amount' => 'nullable|numeric',
            'requested_monetary_totals.charge_total_amount' => 'nullable|numeric',
            'requested_monetary_totals.payable_amount' => 'required|numeric',

            // Debit note lines
            'debit_note_lines' => 'required|array',
            'debit_note_lines.*.unit_measure_id' => 'required|exists:unit_measures,id',
            'debit_note_lines.*.invoiced_quantity' => 'required|numeric',
            'debit_note_lines.*.line_extension_amount' => 'required|numeric',
            'debit_note_lines.*.free_of_charge_indicator' => 'required|boolean',
            'debit_note_lines.*.reference_price_id' => 'nullable|required_if:debit_note_lines.*.free_of_charge_indicator,true|exists:reference_prices,id',
            'debit_note_lines.*.allowance_charges' => 'nullable|array',
            'debit_note_lines.*.allowance_charges.*.charge_indicator' => 'nullable|required_with:debit_note_lines.*.allowance_charges|boolean',
            'debit_note_lines.*.allowance_charges.*.allowance_charge_reason' => 'nullable|required_with:debit_note_lines.*.allowance_charges|string',
            'debit_note_lines.*.allowance_charges.*.amount' => 'nullable|required_with:debit_note_lines.*.allowance_charges|numeric',
            'debit_note_lines.*.allowance_charges.*.base_amount' => 'nullable|required_if:debit_note_lines.*.allowance_charges.*.charge_indicator,false|numeric',
            'debit_note_lines.*.allowance_charges.*.multiplier_factor_numeric' => 'nullable|required_if:debit_note_lines.*.allowance_charges.*.charge_indicator,true|numeric',
            'debit_note_lines.*.tax_totals' => 'nullable|array',
            'debit_note_lines.*.tax_totals.*.tax_id' => 'nullable|required_with:debit_note_lines.*.tax_totals|exists:taxes,id',
            'debit_note_lines.*.tax_totals.*.tax_amount' => 'nullable|required_with:debit_note_lines.*.tax_totals|numeric',
            'debit_note_lines.*.tax_totals.*.taxable_amount' => 'nullable|required_with:debit_note_lines.*.tax_totals|numeric',
            'debit_note_lines.*.tax_totals.*.percent' => 'nullable|required_unless:debit_note_lines.*.tax_totals.*.tax_id,10|numeric',
            'debit_note_lines.*.tax_totals.*.unit_measure_id' => 'nullable|required_if:debit_note_lines.*.tax_totals.*.tax_id,10|exists:unit_measures,id',
            'debit_note_lines.*.tax_totals.*.per_unit_amount' => 'nullable|required_if:debit_note_lines.*.tax_totals.*.tax_id,10|numeric',
            'debit_note_lines.*.tax_totals.*.base_unit_measure' => 'nullable|required_if:debit_note_lines.*.tax_totals.*.tax_id,10|numeric',
            'debit_note_lines.*.description' => 'required|string',
            'debit_note_lines.*.notes' => 'nullable|string',
            'debit_note_lines.*.code' => 'required|string',
            'debit_note_lines.*.type_item_identification_id' => 'required|exists:type_item_identifications,id',
            'debit_note_lines.*.price_amount' => 'required|numeric',
            'debit_note_lines.*.base_quantity' => 'required|numeric',
        ];
    }
}
