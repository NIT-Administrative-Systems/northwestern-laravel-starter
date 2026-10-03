<x-mail::message>
# We received your request

Hi {{ $submitter }},

Thanks for contacting us. Your request is with our team, and someone will follow up with you by email as soon as possible.

<x-mail::panel>
**Reference:** {{ $referenceNumber }}<br>
**Subject:** {{ $subject }}<br>
**Submitted:** {{ $submittedAt }}
</x-mail::panel>

**What you sent**

{!! $details !!}

Please mention your reference number if you contact us about this request.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
