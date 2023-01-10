<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    /**
     * With default model.
     *
     * @var array
     */
    protected $with = [
        'user', 'software', 'certificate', 'resolutions', 'language', 'tax', 'country', 'type_document_identification', 'type_operation', 'type_environment', 'payroll_type_environment','type_currency', 'type_organization', 'municipality', 'type_liability', 'type_regime', 'send',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'identification_number', 'dv', 'language_id', 'tax_id', 'type_environment_id', 'payroll_type_environment_id', 'type_operation_id', 'type_document_identification_id', 'country_id', 'type_currency_id', 'type_organization_id', 'type_regime_id', 'type_liability_id', 'municipality_id', 'merchant_registration', 'address', 'phone', 'municipality_name', 'state_name',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'software', 'certificate', 'resolutions', 'language', 'tax', 'country', 'type_document_identification', 'type_operation', 'type_environment', 'payroll_type_environment', 'type_currency', 'type_organization', 'municipality', 'type_liability', 'type_regime',
    ];

    /**
     * Get the software record associated with the company.
     */
    public function software()
    {
        return $this->hasOne(Software::class);
    }

    /**
     * Get the certificate record associated with the company.
     */
    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    /**
     * Get the resolutions record associated with the company.
     */
    public function resolutions()
    {
        return $this->hasMany(Resolution::class);
    }

    /**
     * Get the language that owns the company.
     */
    public function language()
    {
        return $this->belongsTo(Language::class)
            ->withDefault([
                'id' => 79,
                'name' => 'Spanish; Castilian',
                'code' => 'es',
            ]);
    }

    /**
     * Get the tax that owns the company.
     */
    public function tax()
    {
        return $this->belongsTo(Tax::class)
            ->withDefault([
                'id' => 1,
                'name' => 'IVA',
                'description' => 'Impuesto sobre la Ventas',
                'code' => '01',
            ]);
    }

    /**
     * Get the country that owns the company.
     */
    public function country()
    {
        return $this->belongsTo(Country::class)
            ->withDefault([
                'id' => 46,
                'name' => 'Colombia',
                'code' => 'CO',
            ]);
    }

    /**
     * Get the type operation that owns the company.
     */
    public function type_operation()
    {
        return $this->belongsTo(TypeOperation::class);
    }

    /**
     * Get the type document identification that owns the company.
     */
    public function type_document_identification()
    {
        return $this->belongsTo(TypeDocumentIdentification::class)
            ->withDefault([
                'id' => 3,
                'name' => 'Cédula de ciudadanía',
                'code' => '13',
            ]);
    }

    /**
     * Get the type environment identification that owns the company.
     */
    public function type_environment()
    {
        return $this->belongsTo(TypeEnvironment::class);
    }

    /**
     * Get the payroll_type environment identification that owns the company.
     */
    public function payroll_type_environment()
    {
        return $this->belongsTo(TypeEnvironment::class);
    }

    /**
     * Get the type currency identification that owns the company.
     */
    public function type_currency()
    {
        return $this->belongsTo(TypeCurrency::class);
    }

    /**
     * Get the type organization identification that owns the company.
     */
    public function type_organization()
    {
        return $this->belongsTo(TypeOrganization::class)
            ->withDefault([
                'id' => 2,
                'name' => 'Persona Natural y asimiladas',
                'code' => '2',
            ]);
    }

    /**
     * Get the municipality identification that owns the company.
     */
    public function municipality()
    {
        return $this->belongsTo(Municipality::class)
            ->withDefault([
                'id' => 1006,
                'department_id' => 31,
                'name' => 'Cali',
                'code' => '76001',
            ]);
    }

    /**
     * Get the type liability identification that owns the company.
     */
    public function type_liability()
    {
        return $this->belongsTo(TypeLiability::class)
            ->withDefault([
                'id' => 117,
                'name' => 'No aplica – Otros',
                'code' => 'R-99-PN',
            ]);
    }

    /**
     * Get the type regime identification that owns the company.
     */
    public function type_regime()
    {
        return $this->belongsTo(TypeRegime::class)
            ->withDefault([
                'id' => 2,
                'name' => 'No Responsable de IVA',
                'code' => '49',
            ]);
    }

    /**
     * Get the send that owns the company.
     */
    public function send()
    {
        return $this->hasMany(Send::class)
            ->where('year', now()->format('Y'));
    }

     /**
     * Get the type operation that owns the company.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
