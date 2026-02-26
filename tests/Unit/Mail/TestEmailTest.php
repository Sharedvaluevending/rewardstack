<?php

namespace Tests\Unit\Mail;

use App\Mail\TestEmail;
use Tests\TestCase;

class TestEmailTest extends TestCase
{
    public function test_build_sets_subject_and_view(): void
    {
        $mailable = new TestEmail('Test subject');
        $built = $mailable->build();

        $this->assertSame($mailable, $built);
        $this->assertSame('Test subject', $mailable->subjectLine);
    }

    public function test_build_returns_self(): void
    {
        $mailable = new TestEmail('Subject');
        $this->assertSame($mailable, $mailable->build());
    }
}
