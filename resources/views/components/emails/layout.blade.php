@props([
    'title' => 'Reservation Update',
    'reservation' => null,
    'venue' => null,
    'actionUrl' => null,
    'actionText' => 'View my reservations',
])
@php
    $venue = $venue ?? app(\App\Services\SettingService::class)->venue();
    $venuePhone = $venue['phone'] ?? '(03) 9302 4453';
    $venueAddress = $venue['address'] ?? 'Coolaroo VIC 3048';
    $venueName = $venue['name'] ?? 'Coolaroo Restaurant & Bistro';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title }} — {{ $venueName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F5EFE6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2B1A10;line-height:1.6;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F5EFE6;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(43,26,16,0.08);border:1px solid #EAE0D4;">
                    <tr>
            <td style="background-color:#2B1A10;padding:22px 30px;text-align:center;">
              <h1 style="margin:0;color:#ffffff;font-size:20px;letter-spacing:0.04em;text-transform:uppercase;font-weight:800;">
                COOLAROO
              </h1>
              <div style="color:#FFB627;font-size:10px;letter-spacing:0.25em;text-transform:uppercase;margin-top:3px;font-weight:600;">
                RESTAURANT &amp; BISTRO
              </div>
            </td>
          </tr>

                    <tr>
            <td style="padding:32px 30px 24px 30px;">
              {{ $slot }}

              @if ($reservation)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background-color:#FFF8F0;border-left:4px solid #FF6B2C;border-radius:6px;border-top:1px solid #F0E2D2;border-right:1px solid #F0E2D2;border-bottom:1px solid #F0E2D2;padding:16px 18px;">
                  <tr>
                    <td>
                      <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.06em;color:#6B5647;font-weight:700;margin-bottom:10px;">
                        Booking Summary
                      </div>
                      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.7;">
                        <tr>
                          <td style="color:#6B5647;width:120px;padding:3px 0;">Reference:</td>
                          <td style="font-weight:700;color:#2B1A10;padding:3px 0;">{{ $reservation->reference_code }}</td>
                        </tr>
                        <tr>
                          <td style="color:#6B5647;padding:3px 0;">Date:</td>
                          <td style="font-weight:600;color:#2B1A10;padding:3px 0;">{{ $reservation->booking_date->format('l, j F Y') }}</td>
                        </tr>
                        <tr>
                          <td style="color:#6B5647;padding:3px 0;">Time:</td>
                          <td style="font-weight:600;color:#2B1A10;padding:3px 0;">{{ substr($reservation->booking_time, 0, 5) }}</td>
                        </tr>
                        <tr>
                          <td style="color:#6B5647;padding:3px 0;">Party size:</td>
                          <td style="font-weight:600;color:#2B1A10;padding:3px 0;">{{ $reservation->party_size }} {{ $reservation->party_size === 1 ? 'guest' : 'guests' }}</td>
                        </tr>
                        @if ($reservation->special_requests)
                          <tr>
                            <td style="color:#6B5647;padding:3px 0;vertical-align:top;">Requests:</td>
                            <td style="color:#2B1A10;padding:3px 0;">{{ $reservation->special_requests }}</td>
                          </tr>
                        @endif
                      </table>
                    </td>
                  </tr>
                </table>
              @endif

              @if ($actionUrl)
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0 10px 0;">
                  <tr>
                    <td align="center">
                      <a href="{{ $actionUrl }}" style="display:inline-block;background-color:#FF6B2C;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 28px;border-radius:6px;letter-spacing:0.02em;">
                        {{ $actionText }}
                      </a>
                    </td>
                  </tr>
                </table>
              @endif
            </td>
          </tr>

                    <tr>
            <td style="background-color:#FAF5EE;padding:24px 30px;border-top:1px solid #EAE0D4;font-size:12px;color:#6B5647;line-height:1.7;text-align:center;">
              <div style="font-weight:700;color:#2B1A10;font-size:13px;margin-bottom:4px;">
                {{ $venueName }}
              </div>
              <div>{{ $venueAddress }}</div>
              <div>Phone: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $venuePhone) }}" style="color:#E14D14;text-decoration:none;"><strong>{{ $venuePhone }}</strong></a></div>
              <div style="margin-top:12px;font-size:11px;color:#8C7768;border-top:1px solid #EFE4D6;padding-top:10px;">
                This is an automated notification regarding your table booking. If you have any questions, please contact the venue directly.
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
