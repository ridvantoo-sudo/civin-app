<?php

namespace App\Features\Growth\Mail;

use App\Features\Growth\Models\GrowthCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class CampaignMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public GrowthCampaign $campaign) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('growth.campaigns.from_address'),
                (string) config('growth.campaigns.from_name'),
            ),
            subject: (string) ($this->campaign->subject ?: $this->campaign->name),
        );
    }

    public function content(): Content
    {
        $html = nl2br(e($this->campaign->body));
        if ($this->campaign->action_url) {
            $html .= '<p><a href="'.e($this->campaign->action_url).'">Open</a></p>';
        }

        return new Content(htmlString: $html);
    }
}
