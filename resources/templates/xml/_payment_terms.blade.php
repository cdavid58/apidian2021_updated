<cac:PaymentTerms>
    <cbc:ReferenceEventCode>{{preg_replace("/[\r\n|\n|\r]+/", "", $paymentForm->code)}}</cbc:ReferenceEventCode>
    @if (preg_replace("/[\r\n|\n|\r]+/", "", $paymentForm->code) === '2')
        <cac:SettlementPeriod>
            <cbc:DurationMeasure unitCode="{{preg_replace("/[\r\n|\n|\r]+/", "", $paymentForm->duration_measure_unit_code)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", $paymentForm->duration_measure)}}</cbc:DurationMeasure>
        </cac:SettlementPeriod>
    @endif
</cac:PaymentTerms>
