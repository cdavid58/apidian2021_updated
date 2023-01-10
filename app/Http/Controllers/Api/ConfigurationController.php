<?php

namespace App\Http\Controllers\Api;

use DB;
use Storage;
use App\User;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfigurationRequest;
use App\Http\Requests\Api\ConfigurationSoftwareRequest;
use App\Http\Requests\Api\ConfigurationSoftwarePayrollRequest;
use App\Http\Requests\Api\ConfigurationResolutionRequest;
use App\Http\Requests\Api\ConfigurationCertificateRequest;
use App\Http\Requests\Api\ConfigurationEnvironmentRequest;
use App\Http\Requests\Api\AdministratorRequest;
use App\Http\Requests\Api\ConfigurationLogoRequest;
use App\Http\Requests\Api\ConfigurationInitialDocumentRequest;
use App\Http\Requests\Api\CustomerRequest;
use App\Administrator;
use App\Company;
use App\Document;
use App\Log;
use App\Traits\DocumentTrait;

class ConfigurationController extends Controller
{
    use DocumentTrait;

    public function emailconfig()
    {
        return [
            'message' => 'Configuracion de envios de e-mail',
            'success' => true,
            'MAIL_DRIVER' => env('MAIL_DRIVER'),
            'MAIL_HOST' => env('MAIL_HOST'),
            'MAIL_PORT' => env('MAIL_PORT'),
            'MAIL_USERNAME' => env('MAIL_USERNAME'),
            'MAIL_PASSWORD' => env('MAIL_PASSWORD'),
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION'),
        ];
    }

    /**
     * Store.
     *
     * @param \App\Http\Requests\Api\ConfigurationRequest $request
     * @param int                                         $nit
     * @param int                                         $dv
     *
     * @return \Illuminate\Http\Response
     */
    public function store(ConfigurationRequest $request, $nit, $dv = null)
    {
//        if($this->validarDigVerifDIAN($nit) != $dv)
//            return [
//                'message' => 'Error, el digito de verificacion no es valido para este NIT.',
//                'success' => false,
//            ];

        DB::beginTransaction();

        try {
//            $password = Str::random(80);
            $password = $nit;

            if(count(Company::where('identification_number', '=', $nit)->get()) > 0)
            {
                $operacion = "UPDATE";
                $user = User::where('id', '=', Company::where('identification_number', '=', $nit)->get()->first()->user_id)->get()->first();
            }
            else
                $operacion = "CREATE";

            if($operacion == "CREATE"){
                $user = User::create([
                    'name' => $request->business_name,
                    'email' => $request->email,
                    'password' => bcrypt($password),
                    'id_administrador' => $request->id_administrator ?? 1,
                    'mail_host' => $request->mail_host,
                    'mail_port' => $request->mail_port,
                    'mail_username' => $request->mail_username,
                    'mail_password' => $request->mail_password,
                    'mail_encryption' => $request->mail_encryption,
                ]);

                $user->api_token = hash('sha256', $password);

                $user->company()->create([
                    'user_id' => $user->id,
                    'identification_number' => $nit,
                    'dv' => $dv,
                    'language_id' => $request->language_id ?? 79,
                    'tax_id' => $request->tax_id ?? 1,
                    'type_environment_id' => $request->type_environment_id ?? 2,
                    'payroll_type_environment_id' => $request->payroll_type_environment_id ?? 2,
                    'type_operation_id' => $request->type_operation_id ?? 10,
                    'type_document_identification_id' => $request->type_document_identification_id,
                    'country_id' => $request->country_id ?? 46,
                    'type_currency_id' => $request->type_currency_id ?? 35,
                    'type_organization_id' => $request->type_organization_id,
                    'type_regime_id' => $request->type_regime_id,
                    'type_liability_id' => $request->type_liability_id,
                    'municipality_id' => $request->municipality_id,
                    'merchant_registration' => $request->merchant_registration,
                    'address' => $request->address,
                    'phone' => $request->phone,
                ]);

                $user->save();
            }
            else{
                if(count(User::where('email', '=', $request->email)->where('id', '!=', Company::where('identification_number', '=', $nit)->get()->first()->user_id)->get()) > 0){
                    DB::rollBack();

                    return [
                        'message' => 'Error de registro, el correo electronico ya existe o es el mismo que tiene registrado actualmente.',
                        'success' => false,
                    ];
                }

                $user->update([
                    'name' => $request->business_name,
                    'email' => $request->email,
                    'id_administrator' => $request->id_administrator ?? $user->id_administrator,
                    'mail_host' => $request->mail_host,
                    'mail_port' => $request->mail_port,
                    'mail_username' => $request->mail_username,
                    'mail_password' => $request->mail_password,
                    'mail_encryption' => $request->mail_encryption,
                ]);
                $user->company()->update([
                    'dv' => $dv,
                    'language_id' => $request->language_id ?? 79,
                    'tax_id' => $request->tax_id ?? 1,
//                    'type_environment_id' => $request->type_environment_id ?? 2,
//                    'payroll_type_environment_id' => $request->payroll_type_environment_id ?? 2,
                    'type_operation_id' => $request->type_operation_id ?? 10,
                    'type_document_identification_id' => $request->type_document_identification_id,
                    'country_id' => $request->country_id ?? 46,
                    'type_currency_id' => $request->type_currency_id ?? 35,
                    'type_organization_id' => $request->type_organization_id,
                    'type_regime_id' => $request->type_regime_id,
                    'type_liability_id' => $request->type_liability_id,
                    'municipality_id' => $request->municipality_id,
                    'merchant_registration' => $request->merchant_registration,
                    'address' => $request->address,
                    'phone' => $request->phone,
                ]);
                $user->save();
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Empresa creada/actualizada con éxito',
                'password' => $user->password,
                'token' => $user->api_token,
                'company' => $user->company,
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store software.
     *
     * @param \App\Http\Requests\Api\ConfigurationSoftwareRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeSoftware(ConfigurationSoftwareRequest $request)
    {
        DB::beginTransaction();

        try {
//            auth()->user()->company->software()->delete();
            if(is_null(auth()->user()->company->software))
                $software = auth()->user()->company->software()->create(
                    [
                        'identifier' => $request->id,
                        'pin' => $request->pin,
                        'url' => $request->url ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                        'url_payroll' => '',
                        'identifier_payroll' => '',
                        'pin_payroll' => '',
                    ]
                );
            else
                $software = auth()->user()->company->software()->update(
                    [
                        'identifier' => $request->id,
                        'pin' => $request->pin,
                        'url' => $request->url ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
                        'url_payroll' => '',
                        'identifier_payroll' => '',
                        'pin_payroll' => '',
                    ]
                );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Software creado/actualizado con éxito',
                'software' => $software,
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store payroll software.
     *
     * @param \App\Http\Requests\Api\ConfigurationSoftwarePayrollRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeSoftwarePayroll(ConfigurationSoftwarePayrollRequest $request)
    {
        DB::beginTransaction();

        try {
            $software = auth()->user()->company->software()->update([
                'identifier_payroll' => $request->idpayroll,
                'pin_payroll' => $request->pinpayroll,
                'url_payroll' => $request->url ?? 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Software de nomina creado/actualizado con éxito',
                'software' => auth()->user()->company->software,
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Certificate End Date.
     *
     *
     * @return \Illuminate\Http\Response
     */
    public function CertificateEndDate()
    {
        $company = auth()->user()->company;
        $pfxContent = file_get_contents(storage_path("app/certificates/".$company->certificate->name));
        try {
            if (!openssl_pkcs12_read($pfxContent, $x509certdata, $company->certificate->password)) {
                throw new Exception('The certificate could not be read.');
            }
            else{
                $CertPriv   = array();
                $CertPriv   = openssl_x509_parse(openssl_x509_read($x509certdata['cert']));

                $PrivateKey = $x509certdata['pkey'];

                $pub_key = openssl_pkey_get_public($x509certdata['cert']);
                $keyData = openssl_pkey_get_details($pub_key);

                $PublicKey  = $keyData['key'];

//                return $CertPriv['name'];                           //Nome
//                return $CertPriv['hash'];                           //hash
//                return $CertPriv['subject']['C'];                   //País
//                return $CertPriv['subject']['ST'];                  //Estado
//                return $CertPriv['subject']['L'];                   //Município
//                return $CertPriv['subject']['CN'];                  //Razão Social e CNPJ / CPF
                return date('d/m/Y', $CertPriv['validTo_time_t'] ); //Validade
//                return $CertPriv['extensions']['subjectAltName'];   //Emails Cadastrados separado por ,
//                return $CertPriv['extensions']['authorityKeyIdentifier'];
//                return $CertPriv['issuer'];                   //Emissor
//                return $PublicKey;
//                return $PrivateKey;
            }
        } catch (Exception $e) {
            if (false == ($error = openssl_error_string())) {
                return response([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'certificate' => 'The base64 encoding is not valid.',
                    ],
                ], 422);
            }
            return $pfxContent;
        }
    }


    /**
     * Store certificate.
     *
     * @param \App\Http\Requests\Api\ConfigurationCertificateRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeCertificate(ConfigurationCertificateRequest $request)
    {
        try {
            if (!base64_decode($request->certificate, true)) {
                throw new Exception('The given data was invalid.');
            }
            if (!openssl_pkcs12_read($certificateBinary = base64_decode($request->certificate), $certificate, $request->password)) {
                throw new Exception('The certificate could not be read.');
            }
        } catch (Exception $e) {
            if (false == ($error = openssl_error_string())) {
                return response([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'certificate' => 'The base64 encoding is not valid.',
                    ],
                ], 422);
            }

            return response([
                'message' => $e->getMessage(),
                'errors' => [
                    'certificate' => $error,
                    'password' => $error,
                ],
            ], 422);
        }

        DB::beginTransaction();

        try {
            auth()->user()->company->certificate()->delete();

            $company = auth()->user()->company;
            $name = "{$company->identification_number}{$company->dv}.p12";

            Storage::put("certificates/{$name}", $certificateBinary);

            $certificate = auth()->user()->company->certificate()->create([
                'name' => $name,
                'password' => $request->password,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Certificado creado con éxito',
                'certificado' => $certificate,
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store logo.
     *
     * @param \App\Http\Requests\Api\ConfigurationLogoRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeLogo(ConfigurationLogoRequest $request)
    {
        try {
            if (!base64_decode($request->logo, true)) {
                throw new Exception('The given data was invalid.');
            }
        } catch (Exception $e) {
            return response([
                'message' => $e->getMessage(),
                'errors' => [
                    'logo' => 'The base64 encoding is not valid.',
                ],
            ], 422);

            return response([
                'message' => $e->getMessage(),
                'errors' => [
                    'logo' => $error,
                ],
            ], 422);
        }

        try {

            $company = auth()->user()->company;
            $name = "{$company->identification_number}{$company->dv}.jpg";

            Storage::put("public/{$company->identification_number}/{$name}", base64_decode($request->logo));

            return [
                'success' => true,
                'message' => 'Logo almacenado con éxito',
            ];
        } catch (Exception $e) {

            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store resolution.
     *
     * @param \App\Http\Requests\Api\ConfigurationResolutionRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeResolution(ConfigurationResolutionRequest $request)
    {
        DB::beginTransaction();

        try {
            if($request->delete_all_type_resolutions){
                $resolution = auth()->user()->company->resolutions()->where('type_document_id', $request->type_document_id)->get();
                if(count($resolution) > 0)
                    foreach($resolution as $r)
                        $r->delete();
            }
            $resolution = auth()->user()->company->resolutions()->updateOrCreate([
                'type_document_id' => $request->type_document_id,
                'resolution' => $request->resolution,
                'prefix' => $request->prefix,
            ], [
                'resolution_date' => $request->resolution_date,
                'technical_key' => $request->technical_key,
                'from' => $request->from,
                'to' => $request->to,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Resolución creada/actualizada con éxito',
                'resolution' => $resolution,
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register Customer.
     *
     * @param \App\Http\Requests\Api\CustomerRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function RegCustomer(CustomerRequest $request)
    {
        try{
            $r = $this->registerCustomer($request, $request->sendnotification, true);
            return [
                'success' => true,
                'message' => 'Cliente creado/actualizado con exito.',
            ];
        }
        catch (Exception $e) {
            return [
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ];
        }
    }

    /**
     * Store Initial Document.
     *
     * @param \App\Http\Requests\Api\ConfigurationInitialDocumentRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeInitialDocument(ConfigurationInitialDocumentRequest $request)
    {
        DB::beginTransaction();

        try {
            $initialdocument = Document::updateOrCreate([
                'identification_number' => $request->identification_number,
                'type_document_id' => $request->type_document_id,
                'prefix' => $request->prefix,
                'cufe' => 'cufe-initial-number',
            ],
            [
                'number' => $request->number,
                'state_document_id' => 1,
                'customer' => '222222222222',
                'xml' => 'INITIAL_NUMBER.XML',
                'client_id' => '222222222222',
                'client' => json_encode([]),
                'currency_id' => 35,
                'sale' => 0,
                'total_discount' => 0,
                'taxes' => json_encode([]),
                'total_tax' => 0,
                'subtotal' => 0,
                'total' => 0,
                'version_ubl_id' => 2,
                'ambient_id' => 2,
                'request_api' => json_encode([]),
                'pdf' => 'INITIAL_NUMBER.PDF',
                'date_issue' => date("Y-m-d H:i:s"),
            ]);

            $initialdocument->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Documento Inicial creado/actualizado con éxito',
                'number' => $request->number,
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store environment.
     *
     * @param \App\Http\Requests\Api\ConfigurationEnvironmentRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeEnvironment(ConfigurationEnvironmentRequest $request)
    {
        if(!$request->type_environment_id)
            $request->type_environment_id = auth()->user()->company->type_environment_id;
        if(!$request->payroll_type_environment_id)
            $request->payroll_type_environment_id = auth()->user()->company->payroll_type_environment_id;
        auth()->user()->company->update([
            'type_environment_id' => $request->type_environment_id,
            'payroll_type_environment_id' => $request->payroll_type_environment_id,
        ]);

        if ($request->type_environment_id)
            if ($request->type_environment_id == 1)
              auth()->user()->company->software->update([
                  'url' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc',
                ]);
            else
               auth()->user()->company->software->update([
                  'url' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
              ]);

        if ($request->payroll_type_environment_id)
            if ($request->payroll_type_environment_id == 1)
              auth()->user()->company->software->update([
                  'url_payroll' => 'https://vpfe.dian.gov.co/WcfDianCustomerServices.svc',
                ]);
            else
               auth()->user()->company->software->update([
                  'url_payroll' => 'https://vpfe-hab.dian.gov.co/WcfDianCustomerServices.svc',
              ]);

        return [
            'message' => 'Ambiente actualizado con éxito',
            'company' => auth()->user()->company,
        ];
    }

    /**
    * Borrar Company API
    */
    public function deleteCompany($nit, $dv)
    {
        $company = Company::where('identification_number', '=', $nit)->get()->first();
        $id_user = $company->user_id;
        Company::where('identification_number', $nit)->delete();
        Document::where('identification_number', $nit)->delete();
        Log::where('user_id', $id_user)->delete();
        User::where('id', $id_user)->delete();
        return [
            'message' => 'Empresa eliminada con exito.',
            'success' => true,
        ];
    }

    /**
    * Destroy Company used by FACTURADOR PRO
    */
    public function destroyCompany($nit, $email)
    {
        $id_user = User::select('id')->where('email', $email)->first();
        Company::where('identification_number', $nit)->delete();
        Document::where('identification_number', $nit)->delete();
        Log::where('user_id', $id_user->id)->delete();
        User::where('email', $email)->delete();
    }

    /**
     * storeAdministrator.
     *
     * @param \App\Http\Requests\Api\ConfigurationRequest $request
     *
     * @return \Illuminate\Http\Response
     */
    public function storeAdministrator(AdministratorRequest $request)
    {

        try {
            if($request->password)
                $password = $request->password;
            else
                $password = "12345*";

            $a = Administrator::where('identification_number', $request->identification_number)->get();
            if(count($a) > 0)
                $administrator = Administrator::updateOrCreate([
                    'identification_number' => $request->identification_number,
                ], [
                    'dv' => $request->dv,
                    'name' => $request->name,
                    'address' => $request->address,
                    'phone' => $request->phone,
                    'contact_name' => $request->contact_name,
                    'password' => bcrypt($password),
                    'plan' => $request->plan,
                    'state' => $request->state ?? true,
                    'observation' => $request->observation,
                ]);
            else{
                $administrator = Administrator::updateOrCreate([
                    'identification_number' => $request->identification_number,
                ], [
                    'dv' => $request->dv,
                    'name' => $request->name,
                    'address' => $request->address,
                    'phone' => $request->phone,
                    'email' => $request->email,
                    'contact_name' => $request->contact_name,
                    'password' => bcrypt($password),
                    'plan' => $request->plan,
                    'state' => $request->state ?? true,
                    'observation' => $request->observation,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Administrador creado/actualizado con éxito',
                'password' => $password,
            ];
        } catch (Exception $e) {
            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * queryAdministrator.
     *
     * @param \App\Http\Requests\Api\ConfigurationRequest $request
     * @param int                                         $nit
     *
     * @return \Illuminate\Http\Response
     */
    public function queryAdministrator($nit = FALSE)
    {
        try {
            if($nit)
                $a = Administrator::where('identification_number', $nit)->firstOrFail();
            else
                $a = Administrator::where('identification_number', '>', 0)->get();
            return [
                'success' => true,
                'message' => 'Administrador/es existe/n, informacion del/los administrador/es:',
                'data' => $a
            ];
        } catch (Exception $e) {
            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * queryUsersByAdministrator.
     *
     * @param \App\Http\Requests\Api\ConfigurationRequest $request
     * @param int                                         $nit
     *
     * @return \Illuminate\Http\Response
     */
    public function queryUsersByAdministrator($nit)
    {
        try {
            $a = Administrator::where('identification_number', $nit)->firstOrFail();
            $u = User::where('id_administrator', $a->id)->get();
            return [
                'success' => true,
                'message' => 'Usuarios registrados al administrador NIT: '.$nit,
                'user' => $u,
            ];
        } catch (Exception $e) {
            return response([
                'message' => 'Internal Server Error',
                'payload' => $e->getMessage(),
            ], 500);
        }
    }
}
