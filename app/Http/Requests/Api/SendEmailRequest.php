<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendEmailRequest extends FormRequest
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
            'company_idnumber' => 'nullable|string',
            'prefix' => 'required|string',
            'number' => 'required|string',
            'base64graphicrepresentation' => 'nullable|string',
            'alternate_email' => 'nullable|string|email',

            // HTML string body email
            'html_header' => 'nullable|string',
            'html_body' => 'nullable|string',
            'html_buttons' => 'nullable|string',
            'html_footer' => 'nullable|string',
        ];
    }
}
