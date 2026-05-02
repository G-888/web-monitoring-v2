<h2>⚠️ SSL Certificate Expiring Soon</h2>

<p>The SSL certificate for <strong>{{ $monitor->name }}</strong> is expiring soon.</p>

<p>URL: {{ $monitor->url }}</p>
<p>Expires: {{ $monitor->ssl_expires_at->format('Y-m-d') }} ({{ $daysLeft }} days left)</p>
<p>Issuer: {{ $monitor->ssl_issuer ?? 'Unknown' }}</p>

<p>Please renew your SSL certificate to avoid security warnings and potential downtime.</p>