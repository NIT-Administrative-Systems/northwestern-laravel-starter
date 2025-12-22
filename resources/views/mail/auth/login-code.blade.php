<x-mail::message>
Hello,

We received a request to sign in to your **{{ config('app.name') }}** account.

To continue, please enter the verification code below in the original sign-in window:

<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center">
            <div style="display:inline-block; min-width: 12.5rem; padding: 1rem 1.5rem; border: 1px solid #d1d5db; background: #ffffff; color: #111827; font-size: 2rem; line-height: 2.375rem; font-weight: 600; letter-spacing: 0.35rem; text-align: center;">
                {{ $code }}
            </div>
        </td>
    </tr>
</table>

<x-slot:subcopy>
If you did not initiate this request, you may disregard this email. For your security, do not share this verification code with anyone.
</x-slot:subcopy>
</x-mail::message>
