<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Company;
use App\Document;




class ResumeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }


    public function index()
    {   
       
    }

    public function information($nit, $desde = NULL, $hasta = NULL)
    {
        if($desde && !$hasta)
          $hasta = $desde;
        else
          if(!$desde && $hasta)  
            $desde = $hasta;
          else
            if(!$desde && !$hasta)  
            {
                $desde = '1900-01-01';
                $hasta = '2100-01-01';
            }

        $company = Company::where('identification_number', $nit)->first();

        if(!$company)
        {
            return [
                'success' => false,
                'message' => 'No se encontraron datos del NIT',
            ];
        }

        $invoice = (object)[
            'name' => 'Factura de Venta Nacional',
            'count' => Document::where('identification_number', $company->identification_number)->where('type_document_id', 1)->whereDate('date_issue', '>=', $desde)->whereDate('date_issue', '<=', $hasta)->count()
        ];

        $credit_note = (object)[
            'name' => 'Nota Credito',
            'count' => Document::where('identification_number', $company->identification_number)->where('type_document_id', 4)->whereDate('date_issue', '>=', $desde)->whereDate('date_issue', '<=', $hasta)->count()
        ];

        $debit_note = (object)[
            'name' => 'Nota Debito',
            'count' => Document::where('identification_number', $company->identification_number)->where('type_document_id', 5)->whereDate('date_issue', '>=', $desde)->whereDate('date_issue', '<=', $hasta)->count()
        ];

        return [
            'success' => true,
            'message' => 'NIT Encontrado',
            'data'=> array( $invoice, $credit_note, $debit_note ),
            'companie' => $company->user->name
        ];
    }
   




 

    


}
