<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use \App\Customer;
use Validator;
use App\Traits\DocumentTrait;

class AppServiceProvider extends ServiceProvider
{
    use DocumentTrait;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);

        Validator::extend('passwordcustomer_verify', function($attribute, $value, $parameters, $validator) {
            $customer = Customer::where('identification_number', '=', $parameters[0])->get();
            if(count($customer) > 0)
                if (password_verify($value, $customer[0]->password))
                    return true;
                else
                    return false;
        });

        Validator::extend('igual_a', function($attribute, $value, $parameters, $validator) {
            if($value == $parameters[0])
                return true;
            else
                return false;
        });

        Validator::extend('dian_dv', function($attribute, $value, $parameters, $validator) {
            if($this->validarDigVerifDIAN($parameters[0]) == $value)
                return true;
            else
                return false;
        });
    }
}
