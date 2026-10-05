<b><u>Authenticating with Client Credentials</u></b><br><br>

An integration exchanges the client ID and secret for an access token, then sends the access token with each
API request. Access tokens last one hour; request a new one when it expires. Every token acts as this API user,
with its roles.<br><br>

<b>1. Get an access token</b><br>
<code>POST {{ url('/oauth/token') }}</code> with the form fields
<code>grant_type=client_credentials</code>, <code>client_id</code> and <code>client_secret</code>.
The response's <code>access_token</code> is the token, and <code>expires_in</code> is its lifetime in seconds.<br><br>

<b>2. Call the API</b><br>
Send the token in the HTTP <code>Authorization</code> header:<br><br>

<code>Authorization: Bearer {access_token}</code><br><br>

<b>Through Apigee</b><br>
Most Northwestern integrations reach the application through an Apigee API proxy, which works with the
University's API Service Registry to manage access approvals and consumer onboarding. Store the client ID and
secret in Apigee's Key Value Maps (KVMs), and have the proxy request an access token, cache it until it
expires, and forward it to the application. To give each downstream consumer its own permissions and audit
trail, create an API user and client per consumer and resolve the right credentials from the caller's Apigee
App.<br><br>

<b>Rotating</b><br>
Rotating creates a replacement client while this one keeps working. Update the integration with the new
client ID and secret, then revoke the old client.
