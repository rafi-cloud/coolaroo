<x-layouts.public
    title="Terms & Conditions — {{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }}"
    description="Terms and Conditions of dining, reservations, and QR ordering at Coolaroo Restaurant & Bistro."
>
  <div class="wrap section" data-testid="terms-page">
    <nav class="legal-nav" aria-label="Legal documents">
      <a href="{{ route('privacy') }}" class="legal-nav-link" data-testid="legal-nav-privacy">Privacy Policy</a>
      <a href="{{ route('terms') }}" class="legal-nav-link active" data-testid="legal-nav-terms">Terms &amp; Conditions</a>
    </nav>

    <article class="legal-card">
      <header class="legal-header">
        <h1>Terms &amp; Conditions</h1>
        <p class="legal-meta">Last updated: {{ now()->format('d F Y') }} &bull; Governing Jurisdiction: Victoria, Australia</p>
      </header>

      <div class="legal-body">
        <div class="legal-callout" data-testid="terms-intro-callout">
          <strong>Welcome to {{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }}</strong>
          <p>These Terms &amp; Conditions govern table reservations, digital QR ordering, dining attendance, and payments at our venue. By reserving a table, scanning our table QR codes, or dining with us, you agree to these terms.</p>
        </div>

        <h2>1. Table Reservations &amp; Dining Policy</h2>
        <ul>
          <li><strong>Party Sizes &amp; Booking Horizon:</strong> Online reservations are accepted for parties between 1 and 10 guests up to 60 calendar days in advance. Larger dining groups up to 50 guests may be arranged directly by calling venue staff.</li>
          <li><strong>Dining Durations:</strong> To ensure equitable table access for all guests, standard dining seatings are allocated as follows:
            <ul>
              <li>1 to 2 guests: 90 minutes</li>
              <li>3 to 6 guests: 120 minutes</li>
              <li>7 or more guests: 150 minutes</li>
            </ul>
          </li>
          <li><strong>Arrival Grace Period &amp; Attendance:</strong> Reserved tables will be held for up to 15 minutes past your scheduled reservation time. If your party has not arrived or checked in within the 15-minute grace period, the reservation may be recorded as unattended (no-show) and the table released for other guests.</li>
          <li><strong>Modification &amp; Cancellation Lock:</strong> Reservation details (date, time, party size) may be modified or cancelled online up to 2 hours prior to your scheduled booking time. Modifications and cancellations within 2 hours of booking time are locked and require contacting venue staff directly; late cancellations within 2 hours are recorded in dining attendance logs.</li>
        </ul>

        <h2>2. Table QR Ordering &amp; Digital Service</h2>
        <ul>
          <li><strong>Self-Service Ordering:</strong> Orders placed via table QR codes are transmitted directly to the kitchen display and bar stations for immediate preparation upon successful checkout.</li>
          <li><strong>Pricing &amp; Currency:</strong> All prices displayed on our menu and digital carts are stated in Australian Dollars (AUD) and are inclusive of Australian Goods and Services Tax (GST, 10%).</li>
          <li><strong>Payment Methods &amp; Settlements:</strong> Orders may be paid digitally via Stripe hosted checkout or via cash settlement at your table. Cash transactions are rounded to the nearest 5 cents in accordance with Australian currency convention.</li>
          <li><strong>Order Changes &amp; Cancellations:</strong> Once an order is submitted and payment is authorized, line items enter preparation immediately. Order cancellations or refund requests are subject to management review and approval; food already prepared cannot be refunded.</li>
        </ul>

        <h2>3. Dietary Requirements &amp; Allergen Advisory</h2>
        <div class="legal-callout" data-testid="terms-allergen-callout">
          <strong>Food Allergies &amp; Severe Dietary Restrictions</strong>
          <p>We take food preparation and dietary requirements seriously. Our menu details the 14 standard statutory allergens (celery, gluten, crustaceans, eggs, fish, lupin, milk, molluscs, mustard, nuts, peanuts, sesame, soy, and sulphites) where declared in ingredients.</p>
          <p>However, all dishes are prepared in a commercial kitchen where cross-contamination risks may exist. If you or a guest suffer from a severe or life-threatening allergy, you must inform our serving staff in person prior to placing an order. Our AI dining assistant provides culinary suggestions based on declared tags but does not substitute for medical advice or direct consultation with venue staff.</p>
        </div>

        <h2>4. Responsible Service of Alcohol &amp; Venue Conduct</h2>
        <p>In accordance with Victorian Liquor Control Reform regulations, our staff strictly enforce the Responsible Service of Alcohol (RSA). We reserve the right to request proof of age identification, refuse alcohol service to intoxicated patrons, or request disruptive persons to leave the premises.</p>

        <h2>5. Limitation of Liability &amp; Consumer Guarantees</h2>
        <p>Nothing in these Terms excludes, restricts, or modifies any consumer rights or guarantees under the Australian Consumer Law (Competition and Consumer Act 2010 Cth). To the extent permitted by law, our liability is limited to the resupply of goods or services or reimbursement of amounts paid.</p>

        <h2>6. Contact Us</h2>
        <p>If you have any questions regarding these Terms &amp; Conditions or wish to discuss a reservation, please reach out to our team:</p>
        <div class="legal-callout" data-testid="terms-contact-details">
          <p><strong>{{ $venue['name'] ?? 'Coolaroo Restaurant & Bistro' }}</strong></p>
          <p>Address: {{ $venue['address'] ?? 'Coolaroo, VIC, Australia' }}</p>
          <p>Phone: <a href="tel:{{ preg_replace('/[^\d+]/', '', $venue['phone'] ?? '') }}" data-testid="terms-phone-link">{{ $venue['phone'] ?? '(03) 9000 0000' }}</a></p>
          <p>Email: <a href="mailto:{{ $venue['email'] ?? 'info@coolaroo.local' }}" data-testid="terms-email-link">{{ $venue['email'] ?? 'info@coolaroo.local' }}</a></p>
        </div>
      </div>
    </article>
  </div>
</x-layouts.public>
