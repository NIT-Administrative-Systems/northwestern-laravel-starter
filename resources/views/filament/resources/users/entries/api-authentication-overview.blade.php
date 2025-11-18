<b><u>Authorization with Bearer Tokens</u></b><br><br>

Most Northwestern integrations authenticate through an Apigee API proxy.
Apigee works together with the University's API Service Registry to manage access approvals,
consumer onboarding, and fine-grained authorization controls. In these cases, the proxy is responsible
for forwarding the correct Bearer token to the application.<br><br>

<b>1. Shared System Account (Simple)</b><br>
Store a single Bearer token in Apigee's Key Value Maps (KVMs). All consumers of the proxy use the same
token when Apigee forwards requests to the backend application. This works well when you don't need
per-consumer RBAC or custom access boundaries.<br><br>

<b>2. Per-Consumer Service Accounts (Granular)</b><br>
For more control, assign each downstream consumer its own API user and Bearer token within the application:
<ul>
    <li>&bull;&nbsp;Store each token as a unique entry in Apigee’s KVMs
        (e.g., <code>ConsumerA_Token</code>, <code>ConsumerB_Token</code>)</li>
    <li>&bull;&nbsp;Expose these keys as custom attributes on each Apigee App</li>
    <li>&bull;&nbsp;In your proxy logic, use the caller’s API key to resolve their App attributes
        and retrieve the appropriate token</li>
</ul>
This enables consumer-specific permissions, rate-limits, and auditability through the application’s RBAC model.<br><br>

<b>3. Direct Bearer Authentication (Fallback)</b><br>
If an integration cannot use Apigee, such as certain third-party systems or internal services
without a proxy, you may authenticate directly against the application by including the token
in the HTTP <code>Authorization</code> header:<br><br>

<code>Authorization: Bearer {token}</code><br><br>
