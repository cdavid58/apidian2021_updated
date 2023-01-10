@foreach ($creditNoteLines as $key => $creditNoteLine)
    <cac:CreditNoteLine>
        <cbc:ID>{{preg_replace("/[\r\n|\n|\r]+/", "", ($key + 1))}}</cbc:ID>
        @if(isset($creditNoteLine->notes))
            <cbc:Note>{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->notes)}}</cbc:Note>
        @endif
        <cbc:CreditedQuantity unitCode="{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->unit_measure->code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", number_format($creditNoteLine->invoiced_quantity, 6, '.', ''))}}</cbc:CreditedQuantity>
        @if(isset($idcurrency))
            <cbc:LineExtensionAmount currencyID="{{preg_replace("/[\r\n|\n|\r]+/", "", $idcurrency->code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", number_format($creditNoteLine->line_extension_amount, 2, '.', ''))}}</cbc:LineExtensionAmount>
        @else
            <cbc:LineExtensionAmount currencyID="{{preg_replace("/[\r\n|\n|\r]+/", "", $company->type_currency->code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", number_format($creditNoteLine->line_extension_amount, 2, '.', ''))}}</cbc:LineExtensionAmount>
        @endif
        <cbc:FreeOfChargeIndicator>{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->free_of_charge_indicator)}}</cbc:FreeOfChargeIndicator>
        @if ($creditNoteLine->free_of_charge_indicator === 'true')
            <cac:PricingReference>
                <cac:AlternativeConditionPrice>
                    <cbc:PriceAmount currencyID="{{preg_replace("/[\r\n|\n|\r]+/", "", $company->type_currency->code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", number_format($creditNoteLine->price_amount, 2, '.', ''))}}</cbc:PriceAmount>
                    <cbc:PriceTypeCode>{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->reference_price->code)}}</cbc:PriceTypeCode>
                </cac:AlternativeConditionPrice>
            </cac:PricingReference>
        @endif
        {{-- TaxTotals line --}}
        @include('xml._tax_totals', ['taxTotals' => $creditNoteLine->tax_totals])
        {{-- AllowanceCharges line  --}}
        @include('xml._allowance_charges', ['allowanceCharges' => $creditNoteLine->allowance_charges])
        <cac:Item>
            <cbc:Description>{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->description)}}</cbc:Description>
            <cac:StandardItemIdentification>
                <cbc:ID schemeID="{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->type_item_identification->code)}}" schemeName="{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->type_item_identification->name)}}" schemeAgencyID="{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->type_item_identification->code_agency)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->code)}}</cbc:ID>
            </cac:StandardItemIdentification>
        </cac:Item>
        <cac:Price>
            @if(isset($idcurrency))
                <cbc:PriceAmount currencyID="{{preg_replace("/[\r\n|\n|\r]+/", "", $idcurrency->code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", number_format(($creditNoteLine->free_of_charge_indicator === 'true') ? 0 : $creditNoteLine->price_amount, 2, '.', ''))}}</cbc:PriceAmount>
            @else
                <cbc:PriceAmount currencyID="{{preg_replace("/[\r\n|\n|\r]+/", "", $company->type_currency->code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", number_format(($creditNoteLine->free_of_charge_indicator === 'true') ? 0 : $creditNoteLine->price_amount, 2, '.', ''))}}</cbc:PriceAmount>
            @endif
            <cbc:BaseQuantity unitCode="{{preg_replace("/[\r\n|\n|\r]+/", "", $creditNoteLine->unit_measure->code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", number_format($creditNoteLine->base_quantity, 6, '.', ''))}}</cbc:BaseQuantity>
        </cac:Price>
    </cac:CreditNoteLine>
@endforeach
