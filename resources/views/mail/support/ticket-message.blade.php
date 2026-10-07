<x-mail::message>
# New support request

@if ($fallbackMode)
> **The ticketing system didn't create this ticket.** This email is the fallback, and Sentry has the error.
@endif

<x-mail::panel>
**{{ $subject }}**

<div>{{ $details }}</div>
</x-mail::panel>

<x-mail::table>
| | |
|:--|:--|
| **Reference** | {{ $referenceNumber }} |
| **Environment** | {{ $environment }} |
| **Submitted** | {{ $submittedAt }} |
| **Requester** | {{ $submitterName }} |
| **Email** | [{{ $submitterEmail }}](mailto:{{ $submitterEmail }}) |
| **NetID** | {{ $submitterUsername }} |
@if ($submitterAffiliation)
| **Affiliation** | {{ $submitterAffiliation }} |
@endif
@if (count($submitterDepartments) > 0)
| **{{ count($submitterDepartments) === 1 ? 'Department' : 'Departments' }}** | {{ implode(', ', $submitterDepartments) }} |
@endif
</x-mail::table>

---

<small>
@if ($fallbackMode)
The ticketing system was unavailable, so this email is the only record of the request.
@else
No ticketing system is configured for this environment, so this email is the record of the request.
@endif
</small>
</x-mail::message>
