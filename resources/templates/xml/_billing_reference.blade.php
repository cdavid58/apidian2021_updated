<cac:BillingReference>
    <cac:InvoiceDocumentReference>
        <cbc:ID>{{preg_replace("/[\r\n|\n|\r]+/", "", $billingReference->number)}}</cbc:ID>
        <cbc:UUID schemeName="{{preg_replace("/[\r\n|\n|\r]+/", "", $billingReference->scheme_name)}}">{{preg_replace("/[\r\n|\n|\r]+/", "", $billingReference->uuid)}}</cbc:UUID>
        <cbc:IssueDate>{{preg_replace("/[\r\n|\n|\r]+/", "", $billingReference->issue_date)}}</cbc:IssueDate>
    </cac:InvoiceDocumentReference>
</cac:BillingReference>
