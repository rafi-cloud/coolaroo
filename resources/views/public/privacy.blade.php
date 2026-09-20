<x-layouts.public
    title="Privacy Policy — {{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }}"
    description="Privacy Policy for Coolaroo Restaurant & Bistro under the Australian Privacy Principles (Privacy Act 1988 Cth)."
>
  <div class="wrap section" data-testid="privacy-page">
    <nav class="legal-nav" aria-label="Legal documents">
      <a href="{{ route('privacy') }}" class="legal-nav-link active" data-testid="legal-nav-privacy">Privacy Policy</a>
      <a href="{{ route('terms') }}" class="legal-nav-link" data-testid="legal-nav-terms">Terms &amp; Conditions</a>
    </nav>

    <article class="legal-card">
      <header class="legal-header">
        <h1>Privacy Policy</h1>
        <p class="legal-meta">Last updated: {{ now()->format('d F Y') }} &bull; Compliance: Australian Privacy Principles (Privacy Act 1988 Cth)</p>
      </header>

      <div class="legal-body">
        <div class="legal-callout" data-testid="privacy-compliance-callout">
          <strong>Australian Privacy Principles Commitment (NFR12)</strong>
          <p>{{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }} is committed to protecting your personal privacy in accordance with the Australian Privacy Principles (APPs) contained in the Privacy Act 1988 (Cth). This policy outlines how we handle personal information, disclose dining attendance records, and safeguard your data when you interact with our restaurant, online reservation wizard, table QR ordering, and dining assistant.</p>
        </div>

        <h2>1. Minimal Data Collection Principle</h2>
        <p>We adhere to a strict principle of data minimisation. We collect and process only the personal information strictly necessary to provide hospitality services, process table bookings, prepare culinary orders, comply with allergen safety standards, and execute payments:</p>
        <ul>
          <li><strong>Table Reservations:</strong> Full name, Australian mobile phone number, and email address for booking confirmation and SMS/email reminders, along with dining party size and optional dietary/seating preferences.</li>
          <li><strong>QR Table Ordering:</strong> Table number, selected dishes, add-ons and dietary tags, and transactional payment references. Guests ordering at tables are not required to provide marketing identifiers.</li>
          <li><strong>Customer Accounts:</strong> For guests registering an account, we securely store account credentials (hashed passwords) and order/booking history for their personal account management.</li>
          <li><strong>Billing &amp; Payments:</strong> Card details are processed directly by our PCI-DSS certified payment gateway (Stripe Checkout). Card numbers, expiries, and CVCs are never processed through or stored on our servers.</li>
        </ul>

        <h2>2. Disclosure of Attendance History &amp; Dining Records</h2>
        <p>In accordance with restaurant operational policies and table allocation management, we explicitly disclose that we collect and maintain dining attendance history:</p>
        <ul>
          <li><strong>Dining Visits &amp; Check-ins:</strong> When you check in at our venue via table QR scan or staff host seating, your arrival time, allocated table, and departure time are recorded in our visit logs to optimise restaurant floor capacity and turn-times.</li>
          <li><strong>Reservation Attendance:</strong> We record whether bookings are completed, modified, cancelled, or marked as unattended (no-show).</li>
          <li><strong>Trust &amp; Attendance Profiles:</strong> Past reservation attendance is evaluated to maintain customer trust indicators (such as Regular guest status or Flagged status for repeat no-shows under venue cancellation terms). Attendance records assist staff in providing personalised service, prioritising table requests, and preventing automated booking abuse.</li>
          <li><strong>Correction &amp; Retention:</strong> Attendance records are retained in our operational logs for administrative and auditing purposes in accordance with Australian record-keeping obligations. Customers may request review or correction of inaccurate attendance flags by contacting management.</li>
        </ul>

        <h2>3. Artificial Intelligence &amp; Dining Assistant Privacy</h2>
        <div class="legal-callout" data-testid="privacy-ai-callout">
          <strong>No Personal Data Sent to AI &bull; Ephemeral Processing (BR47, BR48, NFR12)</strong>
          <p>Our interactive dining assistant and meal builder are designed with privacy-by-design safeguards:</p>
          <ul>
            <li><strong>Zero Personal Data Transmitted:</strong> When you consult the AI assistant or use the meal builder, queries sent to external model providers (GitHub Models / OpenAI API) contain <em>only</em> dish names, ingredient tags, price calculations, and the dietary prompt parameters you select. Your name, contact details, table identity, reservation records, and attendance history are <strong>never</strong> transmitted to external AI services.</li>
            <li><strong>Chat Content Not Stored:</strong> Chat queries, conversational messages, and assistant recommendations are ephemeral. Conversational dialogue is processed in real time and is <strong>not permanently stored</strong> in our database, nor linked to your user account or personal profile. Only anonymous aggregate token usage counts are retained in system audit logs for server capacity tracking.</li>
          </ul>
        </div>

        <h2>4. Use and Disclosure of Information</h2>
        <p>Personal information collected by {{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }} is used exclusively for:</p>
        <ul>
          <li>Administering reservations, table seating, and food/beverage preparation;</li>
          <li>Transmitting transactional service communications (booking confirmations, arrival grace alerts, cancellation notices, digital receipts, and password resets);</li>
          <li>Responding to customer enquiries, service requests, and official feedback submissions;</li>
          <li>Satisfying statutory obligations, food safety regulations, and tax reporting requirements under Australian law.</li>
        </ul>
        <p>We do not sell, rent, or trade your personal data to third parties, and we do not send unsolicited marketing communications.</p>

        <h2>5. Data Security and Storage</h2>
        <p>We implement industry-standard administrative, technical, and physical safeguards to prevent unauthorised access, modification, or disclosure of personal data. All public web traffic and real-time broadcasts are encrypted in transit via Transport Layer Security (TLS/HTTPS). Internal database access is restricted by role-based permissions, and administrative changes are subject to immutable audit logging.</p>

        <h2>6. Access, Correction, and Contact Information</h2>
        <p>Under the Australian Privacy Principles (APPs 12 and 13), you have the right to request access to personal information we hold about you and request that any inaccurate, incomplete, or out-of-date information be corrected.</p>
        <p>For any privacy enquiries, access requests, or concerns regarding your attendance history, please contact our Privacy Officer:</p>
        <div class="legal-callout" data-testid="privacy-contact-details">
          <p><strong>{{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }}</strong></p>
          <p>Address: {{ $venue['address'] ?? 'Coolaroo, VIC, Australia' }}</p>
          <p>Phone: <a href="tel:{{ preg_replace('/[^\d+]/', '', $venue['phone'] ?? '') }}" data-testid="privacy-phone-link">{{ $venue['phone'] ?? '(03) 9000 0000' }}</a></p>
          <p>Email: <a href="mailto:{{ $venue['email'] ?? 'privacy@coolaroo.local' }}" data-testid="privacy-email-link">{{ $venue['email'] ?? 'privacy@coolaroo.local' }}</a></p>
        </div>
      </div>
    </article>
  </div>
</x-layouts.public>
