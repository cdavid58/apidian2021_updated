<?php
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// UBL 2.1
Route::prefix('/ubl2.1')->group(function () {
    Route::get('/emailconfig', 'Api\ConfigurationController@emailconfig');

    // Configuration
    Route::prefix('/config')->group(function () {
        Route::post('/{nit}/{dv?}', 'Api\ConfigurationController@store');
        Route::post('/delete/{nit}/{email}', 'Api\ConfigurationController@destroyCompany');
    });

    // Administrador
    Route::put('/administrator', 'Api\ConfigurationController@storeAdministrator');
    Route::get('/administrator/query/{nit}', 'Api\ConfigurationController@queryAdministrator');
    Route::get('/administrator/query', 'Api\ConfigurationController@queryAdministrator');
    Route::get('/administrator/queryusersbyadmin/{nit}', 'Api\ConfigurationController@queryUsersByAdministrator');

    // Sign Document XML
    Route::prefix('/signdocument')->group(function () {
        Route::post('/', 'Api\SignDocumentController@signdocument');
    });

    // Send Document XML
    Route::prefix('/senddocument')->group(function () {
        Route::post('/', 'Api\SendDocumentController@senddocument');
    });

    Route::prefix('/statusdocument')->group(function () {
        Route::post('/', 'Api\StatusDocumentController@statusdocument');
    });

    Route::prefix('/statuszip')->group(function () {
        Route::post('/', 'Api\StatusZipController@statuszip');
    });
});

Route::middleware('auth:api')->group(function () {

    Route::get('reload-pdf/{identification}/{file}/{cufe}', 'Api\DownloadController@reloadPdf');

    // UBL 2.1
    Route::prefix('/ubl2.1')->group(function () {
        // Join PDFs
        Route::post('/join-pdfs', 'Api\MiscelaneousController@joinPDFs');

        Route::get('/name-by-nit/{nit}', 'Api\MiscelaneousController@nameByNit');

        // Register Customer
        Route::put('/register-update-customer', 'Api\ConfigurationController@RegCustomer');

        // Certificate End Date
        Route::put('/certificate-end-date', 'Api\ConfigurationController@CertificateEndDate');

        // Configuration
        Route::prefix('/config')->group(function () {
            Route::put('/software', 'Api\ConfigurationController@storeSoftware');
            Route::put('/softwarepayroll', 'Api\ConfigurationController@storeSoftwarePayroll');
            Route::put('/certificate', 'Api\ConfigurationController@storeCertificate');
            Route::put('/resolution', 'Api\ConfigurationController@storeResolution');
            Route::put('/environment', 'Api\ConfigurationController@storeEnvironment');
            Route::put('/logo', 'Api\ConfigurationController@storeLogo');
            Route::put('/generateddocuments', 'Api\ConfigurationController@storeInitialDocument');
        });

        Route::prefix('/delete')->group(function () {
            Route::post('/company/{nit}/{dv}', 'Api\ConfigurationController@deleteCompany');
        });

        // Invoice
        Route::prefix('/invoice')->group(function () {
            Route::post('/{testSetId}', 'Api\InvoiceController@testSetStore');
            Route::post('/', 'Api\InvoiceController@store');
            Route::get('/current_number/{type}/{prefix?}', 'Api\InvoiceController@currentNumber');
            Route::get('/state_document/{type}/{number}', 'Api\InvoiceController@changestateDocument');
        });

        // Export Invoice
        Route::prefix('/invoice-export')->group(function () {
            Route::post('/{testSetId}', 'Api\InvoiceExportController@testSetStore');
            Route::post('/', 'Api\InvoiceExportController@store');
        });

        // Contingency Invoice
        Route::prefix('/invoice-contingency')->group(function () {
            Route::post('/{testSetId}', 'Api\InvoiceContingencyController@testSetStore');
            Route::post('/', 'Api\InvoiceContingencyController@store');
        });

        // AUI Invoice
        Route::prefix('/invoice-aiu')->group(function () {
            Route::post('/{testSetId}', 'Api\InvoiceAIUController@testSetStore');
            Route::post('/', 'Api\InvoiceAIUController@store');
        });

        // Mandate Invoice
        Route::prefix('/invoice-mandate')->group(function () {
            Route::post('/{testSetId}', 'Api\InvoiceMandateController@testSetStore');
            Route::post('/', 'Api\InvoiceMandateController@store');
        });

        // Credit Notes
        Route::prefix('/credit-note')->group(function () {
            Route::post('/{testSetId}', 'Api\CreditNoteController@testSetStore');
            Route::post('/', 'Api\CreditNoteController@store');
        });

        // Debit Notes
        Route::prefix('/debit-note')->group(function () {
            Route::post('/{testSetId}', 'Api\DebitNoteController@testSetStore');
            Route::post('/', 'Api\DebitNoteController@store');
        });

        // Support Document
        Route::prefix('/support-document')->group(function () {
            Route::post('/{testSetId}', 'Api\SupportDocumentController@testSetStore');
            Route::post('/', 'Api\SupportDocumentController@store');
        });

        // Add to batch
        Route::prefix('/add-to-batch')->group(function () {
            Route::post('/invoice/{batch}', 'Api\BatchController@addinvoice');
            Route::post('/invoice-aiu/{batch}', 'Api\BatchController@addinvoiceaiu');
            Route::post('/invoice-mandate/{batch}', 'Api\BatchController@addinvoicemandate');
            Route::post('/invoice-export/{batch}', 'Api\BatchController@addinvoiceexport');
            Route::post('/invoice-contingency/{batch}', 'Api\BatchController@addinvoicecontingency');
            Route::post('/credit-note/{batch}', 'Api\BatchController@addcreditnote');
            Route::post('/debit-note/{batch}', 'Api\BatchController@adddebitnote');
        });

        // Send batch
        Route::post('send-batch/{batch}', 'Api\BatchController@sendbatch');

        // Status
        Route::prefix('/status')->group(function () {
            Route::post('/zip/{trackId}/{GuardarEn?}', 'Api\StateController@zip');
            Route::post('/document/{trackId}/{GuardarEn?}', 'Api\StateController@document');
        });

        // Numbering Ranges
        Route::prefix('/numbering-range')->group(function () {
            Route::post('/', 'Api\NumberingRangeController@NumberingRange');
        });

        // Send email
        Route::prefix('/send-email')->group(function () {
            Route::post('/', 'Api\SendEmailController@SendEmail');
        });

        // envio de correo nomina - pro 2
        Route::post('send-email-document-payroll', 'Api\SendEmailController@sendEmailDocumentPayroll');

        // Send email utilizado por el facturador pro 1

        Route::post('send_mail', 'EmailController@send');

        // Send event
        Route::prefix('/send-event')->group(function () {
            Route::post('/', 'Api\SendEventController@sendevent');
        });

        // Payroll
        Route::prefix('/payroll')->group(function () {
            Route::post('/{testSetId}', 'Api\PayrollController@testSetStore');
            Route::post('/', 'Api\PayrollController@store');
            Route::get('/current_number/{type}/{prefix?}', 'Api\PayrollController@currentNumber');
        });

        // Payroll Adjust Note
        Route::prefix('/payroll-adjust-note')->group(function () {
            Route::post('/{testSetId}', 'Api\PayrollAdjustNoteController@testSetStore');
            Route::post('/', 'Api\PayrollAdjustNoteController@store');
        });

        Route::get('download/{identification}/{file}/{type_response?}', 'Api\DownloadController@publicDownload');

    });
});

Route::get('invoice/xml/{filename}', function($fisicroute)
{
    $path = storage_path($fisicroute);
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/xml'
    ]);
});

Route::get('invoice/pdf/{filename}', function($fisicroute)
{
    $path = storage_path("app/".$fisicroute);
    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/pdf'
    ]);
});

Route::get('invoice/{identification}/{filename}', function($identification, $filename)
{
    $path = storage_path("app/public/".$identification."/".$filename);
//    return response(base64_encode(file_get_contents($path)), 200);
    return response()->download($path);
});

Route::prefix('/information')->group(function () {
    Route::get('/{nit}', 'ResumeController@information');
    Route::get('/{nit}/{desde}', 'ResumeController@information');
    Route::get('/{nit}/{desde}/{hasta}', 'ResumeController@information');
});

// Send email change customer password
Route::prefix('/change-customer-password')->group(function () {
    Route::post('/{customer_idnumber}/{show_view}', 'CustomerLoginController@RetrievePassword');
});

// Send email
Route::post('/send-email-customer', 'Api\SendEmailController@SendEmailCustomer')->name('send-email-customer');
Route::post('/send-email-customer/{ShowView}', 'Api\SendEmailController@SendEmailCustomer')->name('send-email-customer-view');

// Add customers/documents from xml
Route::post('/add-customers-documentos-xml/{nit}', 'Api\AddCostumersDocumentsXML@Organize')->name('add-customers-documentos-xml');

Route::post('/accept-reject-document', 'AcceptRejectDocumentController@ExecuteAcceptRejectDocument')->name('acceptrejectdocument');

Route::post('/download-file', 'AcceptRejectDocumentController@DownloadFile')->name('downloadfile');

if(env('ALLOW_PUBLIC_DOWNLOAD', TRUE)){
    Route::get('download/{identification}/{file}/{type_response?}',
        function($identification, $file, $type_response = FALSE)
        {
            $u = new \App\Utils;
            if(strpos($file, 'Attachment-') === false and strpos($file, 'ZipAttachm-') === false)
                if(file_exists(storage_path("app/public/{$identification}/{$file}")))
                    if($type_response && $type_response === 'BASE64')
                        return [
                            'success' => true,
                            'message' => "Archivo: ".$file." se encontro.",
                            'filebase64'=>base64_encode(file_get_contents(storage_path("app/public/{$identification}/{$file}")))
                        ];
                    else
                        return Storage::download("public/{$identification}/{$file}");
                else
                    return [
                        'success' => false,
                        'message' => "No se encontro el archivo: ".$file
                    ];
            else{
                if(strpos($file, 'ZipAttachm-') === false){
                    $filename = $u->attacheddocumentname($identification, $file);
                    if(file_exists(storage_path("app/public/{$identification}/{$filename}.xml")))
                        if($type_response && $type_response === 'BASE64')
                            return [
                                'success' => true,
                                'message' => "Archivo: ".$filename.".xml se encontro.",
                                'filebase64'=>base64_encode(file_get_contents(storage_path("app/public/{$identification}/{$filename}.xml")))
                            ];
                        else
                            return Storage::download("public/{$identification}/{$filename}.xml");
                    else
                        return [
                            'success' => false,
                            'message' => "No se encontro el archivo: ".$filename.".xml"
                        ];
                }
                else{
                    $filename = $u->attacheddocumentname($identification, $file);
                    if(file_exists(storage_path("app/public/{$identification}/{$filename}.zip")))
                        if($type_response && $type_response === 'BASE64')
                            return [
                                'success' => true,
                                'message' => "Archivo: ".$filename.".zip se encontro.",
                                'filebase64'=>base64_encode(file_get_contents(storage_path("app/public/{$identification}/{$filename}.zip")))
                            ];
                        else
                            return Storage::download("public/{$identification}/{$filename}.zip");
                    else
                        return [
                            'success' => false,
                            'message' => "No se encontro el archivo: ".$filename.".zip"
                        ];
                }
            }
        }
    );
}
