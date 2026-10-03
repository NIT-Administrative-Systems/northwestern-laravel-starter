<x-mail::message>
Hello,

We received a request to sign in to your **{{ config('app.name') }}** account.

To continue, enter this verification code on the sign-in page:

<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center">
            <div style="display:inline-block; min-width: 12.5rem; padding: 1rem 1.5rem; border: 1px solid #d1d5db; background: #ffffff; color: #111827; font-size: 2rem; line-height: 2.375rem; font-weight: 600; letter-spacing: 0.35rem; text-align: center;">
                {{ $code }}
            </div>
        </td>
    </tr>
</table>

If you closed the sign-in page, or you are reading this on another device, this button opens it ready for the code:

<x-mail::button :url="$signInUrl">
Enter your code
</x-mail::button>

<x-slot:subcopy>
If you did not initiate this request, you may disregard this email. For your security, do not share this verification code with anyone. This code expires in {{ $expiresInMinutes }} minutes.
</x-slot:subcopy>
</x-mail::message>
