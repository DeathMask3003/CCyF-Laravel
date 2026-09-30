<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IntegrationConfigurationTest extends TestCase
{
    public function test_mail_test_requires_an_explicit_recipient_and_smtp_configuration(): void
    {
        $this->artisan('ccyf:mail-test', ['recipient' => 'invalid'])
            ->assertFailed();

        config()->set('mail.mailers.smtp.host', 'smtp.office365.com');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.username', 'remitente@example.test');
        config()->set('mail.mailers.smtp.password', null);
        config()->set('mail.from.address', 'remitente@example.test');

        $this->artisan('ccyf:mail-test', ['recipient' => 'control@example.test'])
            ->assertFailed();
    }

    public function test_mail_test_does_not_change_the_sites_log_mailer(): void
    {
        config()->set('mail.default', 'log');
        config()->set('mail.mailers.smtp.host', 'smtp.office365.com');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.username', 'remitente@example.test');
        config()->set('mail.mailers.smtp.password', 'test-secret');
        config()->set('mail.from.address', 'remitente@example.test');
        Mail::fake();

        $this->artisan('ccyf:mail-test', ['recipient' => 'control@example.test'])
            ->assertSuccessful();

        $this->assertSame('log', config('mail.default'));
    }
}
