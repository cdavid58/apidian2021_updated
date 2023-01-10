<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
/**/
Auth::routes();
Route::get('/customerlogin/{company_idnumber}/{customer_idnumber}', 'CustomerLoginController@ShowLoginForm');
Route::post('/okcustomerlogin/{company_idnumber}/{customer_idnumber}', 'CustomerLoginController@PasswordVerify')->name('homecustomers');
Route::get('/customer-password/{company_idnumber}/{customer_idnumber}', 'CustomerLoginController@CustomerPassword')->name('customer-password');
Route::post('/reset-customer-password/{company_idnumber}/{customer_idnumber}', 'CustomerLoginController@ResetCustomerPassword')->name('resetcustomerpassword');
Route::get('/retrieve-password/{customer_idnumber}', 'CustomerLoginController@RetrievePassword')->name('retrievepassword');
Route::get('/accept-retrieve-password/{customer_idnumber}/{hash}', 'CustomerLoginController@AcceptRetrievePassword')->name('acceptretrievepassword');
Route::get('/accept-reject-document/{company_idnumber}/{customer_idnumber}/{prefix}/{docnumber}/{issuedate}', 'AcceptRejectDocumentController@ShowViewAcceptRejectDocument');

Route::get('/listings', 'ListingController@index');
Route::get('/portal', 'ListingController@index');
Route::get('/', 'HomeController@index')->name('home');

Route::group(['middleware' => 'auth'], function() {
    Route::get('/home', 'HomeController@index')->name('home');
    Route::get('/documents', 'HomeController@listDocuments')->name('listdocuments');
    Route::get('/taxes', 'HomeController@listTaxes')->name('listtaxes');
    Route::get('/listconfigurations', 'HomeController@listConfigurations')->name('listconfigurations');

    //configuration
    Route::get('/configuration', 'ConfigurationController@index')->name('configuration_index');
    Route::get('/configuration_admin', 'ConfigurationController@configuration_admin')->name('configuration_admin');
    Route::post('/configuration', 'ConfigurationController@store')->name('configuration_store');
    Route::get('configuration/tables', 'ConfigurationController@tables');
    Route::get('configuration/records', 'ConfigurationController@records');

    Route::get('tax', 'TaxController@index')->name('tax_index');
    Route::get('tax/records', 'TaxController@records');

    Route::get('documents', 'DocumentController@index')->name('documents_index');
    Route::get('documents/records', 'DocumentController@records');
    Route::get('documents/downloadxml/{xml}', 'DocumentController@downloadxml');
    Route::get('documents/downloadpdf/{pdf}', 'DocumentController@downloadpdf');

});

Route::get('qr', 'QrController@generateQr');
Route::get('pdf','ListingController@generatePDF');
Route::get('/listings', 'ListingController@index');

Route::get('invoice/xml/{filename}', function($fisicroute)
{
    $path = storage_path($fisicroute);
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/xml'
    ]);
});

Route::get('invoice/pdf/{filename}', function($fisicroute)
{
    $path = storage_path("facturas/pdf/".$fisicroute);
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/pdf'
    ]);
});

//mail test
Route::get('laravel-send-email', 'EmailController@sendEMail');
















