<?php

namespace jdavidbakr\MailTracker;

use Event;

use App\Http\Requests;
use Illuminate\Http\Request;
use GuzzleHttp\Client as Guzzle;
use Aws\Sns\Message as SNSMessage;
use Illuminate\Routing\Controller;
use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\RecordBounceJob;
use jdavidbakr\MailTracker\RecordComplaintJob;
use Aws\Sns\MessageValidator as SNSMessageValidator;
use jdavidbakr\MailTracker\Events\EmailDeliveredEvent;
use jdavidbakr\MailTracker\Events\ComplaintMessageEvent;
use jdavidbakr\MailTracker\Events\PermanentBouncedMessageEvent;

class SNSController extends Controller
{
    public function callback(Request $request)
    {
        if (config('app.env') != 'production' && $request->message) {
            // phpunit cannot mock static methods so without making a facade
            // for SNSMessage we have to pass the json data in $request->message
            $message = new SNSMessage(json_decode($request->message, true));
        } else {
            $message = SNSMessage::fromRawPostData();
            $validator = app(SNSMessageValidator::class);
            $validator->validate($message);
        }
        // If we have a topic defined, make sure this is that topic
        if (config('mail-tracker.sns-topic') && $message->offsetGet('TopicArn') != config('mail-tracker.sns-topic')) {
            return 'invalid topic ARN';
        }

        switch ($message->offsetGet('Type')) {
            case 'SubscriptionConfirmation':
                return $this->confirm_subscription($message);
            case 'Notification':
                return $this->process_notification($message);
        }
    }

    protected function confirm_subscription($message)
    {
        $client = new Guzzle();
        $client->get($message->offsetGet('SubscribeURL'));
        return 'subscription confirmed';
    }

    protected function process_notification($message)
    {
        $message = json_decode($message->offsetGet('Message'));
        switch ($message->notificationType) {
            case 'Delivery':
                $this->process_delivery($message);
                break;
            case 'Bounce':
                $this->process_bounce($message);
                break;
            case 'Complaint':
                $this->process_complaint($message);
                break;
        }
        return 'notification processed';
    }

    protected function process_delivery($message)
    {
        $sent_email = SentEmail::where('message_id', $message->mail->messageId)->first();
        if ($sent_email) {
            $meta = collect($sent_email->meta);
            $meta->put('smtpResponse', $message->delivery->smtpResponse);
            $meta->put('success', true);
            $meta->put('delivered_at', $message->delivery->timestamp);
            $meta->put('sns_message_delivery', $message); // append the full message received from SNS to the 'meta' field
            $sent_email->meta = $meta;
            $sent_email->save();
        }

        foreach ($message->delivery->recipients as $recipient) {
            Event::dispatch(new EmailDeliveredEvent($recipient, $sent_email));
        }
    }

    public function process_bounce($message)
    {
        RecordBounceJob::dispatch($message);
    }

    public function process_complaint($message)
    {
        RecordComplaintJob::dispatch($message);
    }
}
