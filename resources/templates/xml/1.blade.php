
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<NominaIndividual
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
    xmlns:xades="http://uri.etsi.org/01903/v1.3.2#"
    xmlns:xades141="http://uri.etsi.org/01903/v1.4.1#"
    xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"
    xmlns="urn:dian:gov:co:facturaelectronica:NominaIndividual">
    {{-- UBLExtensions --}}
    @include('xml._ubl_extensions_payroll')
    @if(isset($novelty))
        <Novedad CUNENov="{{$novelty->uuidnov}}">{{preg_replace("/[\r\n|\n|\r]+/", "", json_encode($novelty->novelty))}}</Novedad>
    @endif
    {{-- Period --}}
    @include('xml._period_payroll')
    {{-- Secuence Number --}}
    @include('xml._secuence_number_payroll')
    {{-- XML Generation Place --}}
    @include('xml._generation_place_payroll')
    {{-- XML Provider --}}
    @include('xml._provider_payroll')
    {{-- General Information --}}
    @include('xml._general_information_payroll')
    {{-- Employer --}}
    @include('xml._employer')
    {{-- Worker --}}
    @include('xml._worker')
    {{-- Payment --}}
    @include('xml._payment_payroll')
    {{-- Accrued --}}
    @include('xml._accrued_payroll')
    {{-- Deductions --}}
    @include('xml._deductions_payroll')
    <DevengadosTotal>{{preg_replace("/[\r\n|\n|\r]+/", "", $accrued->accrued_total)}}</DevengadosTotal>
    <DeduccionesTotal>{{preg_replace("/[\r\n|\n|\r]+/", "", $deductions->deductions_total)}}</DeduccionesTotal>
    <ComprobanteTotal>{{preg_replace("/[\r\n|\n|\r|',']+/", "", number_format($accrued->accrued_total - $deductions->deductions_total, 2))}}</ComprobanteTotal>
</NominaIndividual>
