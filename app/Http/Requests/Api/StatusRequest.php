<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StatusRequest extends FormRequest
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
            // Consulta de nomina
            'is_payroll' => 'nullable|boolean',

            // Prefijo del Nombre del AttachedDocument
            'atacheddocument_name_prefix' => 'nullable|string',

            // Comportamiento de Envios de Correo.
            'sendmail' => 'nullable|boolean',
            'sendmailtome' => 'nullable|boolean',

            // Lista de correos a enviar copia
            'email_cc_list' => 'nullable|array',
            'email_cc_list.*.email' => 'nullable|required_with:email_cc_list,|string|email',

            // HTML string body email
            'html_header' => 'nullable|string',
            'html_body' => 'nullable|string',
            'html_buttons' => 'nullable|string',
            'html_footer' => 'nullable|string',
        ];
    }
}
