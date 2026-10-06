<x-mail::message>
# We received your request

Hello{{ filled($submitter) ? " {$submitter}" : '' }},

Thanks for contacting us. Your request is with our team, and someone will follow up with you as soon as possible.

<x-mail::panel>
**Reference:** {{ $referenceNumber }}<br>
**Subject:** {{ $subject }}<br>
**Submitted:** {{ $submittedAt }}
</x-mail::panel>

**What you sent**

{!! $details !!}

---

Mention your reference number if you contact us about this request.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
