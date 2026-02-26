<?php

namespace Tests\Unit\Mail;

use App\Mail\PromoLinkEmail;
use Tests\TestCase;

class PromoLinkEmailTest extends TestCase
{
    public function test_build_sets_subject_from_business_name(): void
    {
        $mailable = new PromoLinkEmail('ABC-123', 'Summer Sale', 'Acme Coffee');
        $built = $mailable->build();

        $this->assertSame($mailable, $built);
        $this->assertSame('Your promo from Acme Coffee', $mailable->subject);
    }

    public function test_build_uses_correct_views(): void
    {
        $mailable = new PromoLinkEmail('CODE-1', 'Promo', 'Biz');
        $mailable->build();

        $this->assertSame('emails.promo_link', $mailable->view);
        $this->assertSame('emails.promo_link_plain', $mailable->textView);
    }

    public function test_mailable_has_public_properties(): void
    {
        $mailable = new PromoLinkEmail('UP-XXXX-YYYY', '20% Off', 'Test Business');
        $this->assertSame('UP-XXXX-YYYY', $mailable->code);
        $this->assertSame('20% Off', $mailable->promotionName);
        $this->assertSame('Test Business', $mailable->businessName);
    }
}
