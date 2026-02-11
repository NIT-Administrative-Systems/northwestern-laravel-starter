<x-mail::message>
Hi {{ $submitter }},

We received your support request and it has been assigned to our team.

**Reference:** {{ $referenceNumber }}

**Subject:** {{ $subject }}

A team member will review your request and follow up with you as soon as possible.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
