<cac:{{preg_replace("/[\r\n|\n|\r]+/", "", $node)}}>
    <cbc:StartDate>{{preg_replace("/[\r\n|\n|\r]+/", "", $healthfields->invoice_period_start_date)}}</cbc:StartDate>
    <cbc:EndDate>{{preg_replace("/[\r\n|\n|\r]+/", "", $healthfields->invoice_period_end_date)}}</cbc:EndDate>
</cac:{{$node}}>
