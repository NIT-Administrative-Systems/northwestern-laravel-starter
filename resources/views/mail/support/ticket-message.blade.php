<x-mail::message>
@if ($fallbackMode)
> **Fallback Alert:** The primary gateway failed to create this ticket.
> This email was sent as a fallback — check Sentry for the corresponding error.
@endif

<x-mail::table>
| | |
|:--|:--|
| **Reference** | {{ $referenceNumber }} |
| **Environment** | {{ $environment }} |
| **Submitted** | {{ now()->setTimezone(config('app.schedule_timezone'))->format('M j, Y g:i A T') }} |
| **Requestor** | {{ $submitterName }} |
| **Email** | [{{ $submitterEmail }}](mailto:{{ $submitterEmail }}) |
| **NetID** | {{ $submitterUsername }} |
@if ($submitterAffiliation)
| **Affiliation** | {{ $submitterAffiliation }} |
@endif
@if (count($submitterDepartments) > 0)
| **{{ count($submitterDepartments) === 1 ? 'Department' : 'Departments' }}** | {{ implode(', ', $submitterDepartments) }} |
@endif
</x-mail::table>

**Subject**

{{ $subject }}

**Details**

{!! $details !!}

---

<small>
@if ($fallbackMode)
This email was generated because the primary ticket system gateway was unavailable.
The request has been captured, but the ticket was not created in the external system.
@else
This email serves as the support ticket record. No external ticketing system is configured for this environment.
@endif
</small>
</x-mail::message>
