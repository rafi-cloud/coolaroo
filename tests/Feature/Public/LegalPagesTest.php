<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_page_is_accessible_and_discloses_nfr12_requirements(): void
    {
        $response = $this->get(route('privacy'));

        $response->assertOk();
        $response->assertViewIs('public.privacy');

        $response->assertSee('Privacy Policy');
        $response->assertSee('Australian Privacy Principles');
        $response->assertSee('Attendance History', false);
        $response->assertSee('Minimal Data Collection Principle', false);
        $response->assertSee('No Personal Data Sent to AI', false);
        $response->assertSee('Chat Content Not Stored', false);

        $response->assertSee('data-testid="privacy-page"', false);
        $response->assertSee('data-testid="privacy-compliance-callout"', false);
        $response->assertSee('data-testid="privacy-ai-callout"', false);
        $response->assertSee('data-testid="privacy-contact-details"', false);
        $response->assertSee('data-testid="legal-nav-privacy"', false);
        $response->assertSee('data-testid="legal-nav-terms"', false);
    }

    public function test_terms_page_is_accessible_and_renders_operational_and_dining_rules(): void
    {
        $response = $this->get(route('terms'));

        $response->assertOk();
        $response->assertViewIs('public.terms');

        $response->assertSee('Terms &amp; Conditions', false);
        $response->assertSee('Arrival Grace Period', false);
        $response->assertSee('15 minutes', false);
        $response->assertSee('Dining Durations', false);
        $response->assertSee('Table QR Ordering', false);
        $response->assertSee('GST', false);
        $response->assertSee('Allergen Advisory', false);

        $response->assertSee('data-testid="terms-page"', false);
        $response->assertSee('data-testid="terms-intro-callout"', false);
        $response->assertSee('data-testid="terms-allergen-callout"', false);
        $response->assertSee('data-testid="terms-contact-details"', false);
        $response->assertSee('data-testid="legal-nav-privacy"', false);
        $response->assertSee('data-testid="legal-nav-terms"', false);
    }

    public function test_site_footer_contains_links_to_privacy_and_terms_pages(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('privacy'));
        $response->assertSee(route('terms'));
        $response->assertSee('data-testid="site-footer-privacy-link"', false);
        $response->assertSee('data-testid="site-footer-terms-link"', false);
    }
}
