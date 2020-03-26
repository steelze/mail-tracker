<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Event;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\RecordBounceJob;
use jdavidbakr\MailTracker\RecordDeliveryJob;
use jdavidbakr\MailTracker\RecordComplaintJob;
use jdavidbakr\MailTracker\Events\EmailDeliveredEvent;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;

class RecordDeliveryJobTest extends SetUpTest
{
    /**
     * @test
     */
    public function it_marks_the_email_as_unsuccessful()
    {
        Event::fake();
        $track = SentEmail::create([
                'hash' => Str::random(32),
            ]);
        $message_id = Str::uuid();
        $track->message_id = $message_id;
        $track->save();
        $message = (object)[
            'mail' => (object)[
                'messageId' => $message_id,
            ],
            'delivery' => (object)[
                'timestamp' => 12345,
                'recipients' => (object)[
                    'recipient@example.com'
                ],
                'smtpResponse' => 'the smtp response',
            ]
        ];
        $job = new RecordDeliveryJob($message);

        $job->handle();

        $track = $track->fresh();
        $meta = $track->meta;
        $this->assertEquals('the smtp response', $meta->get('smtpResponse'));
        $this->assertTrue($meta->get('success'));
        $this->assertEquals(12345, $meta->get('delivered_at'));
        $this->assertEquals(json_decode(json_encode($message), true), $meta->get('sns_message_delivery'));
        Event::assertDispatched(EmailDeliveredEvent::class, function ($event) use ($track) {
            return $event->email_address == 'recipient@example.com' &&
                $event->sent_email->hash == $track->hash;
        });
    }
}
